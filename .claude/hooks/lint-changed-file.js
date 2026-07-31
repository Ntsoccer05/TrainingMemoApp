#!/usr/bin/env node
// PostToolUse (Edit|Write) hook.
// 変更されたファイル1件だけを対象に静的解析を実行し、既存コードの大量違反をノイズとして
// 出さずに「今回触った差分」だけを機械的にチェックする。詳細背景: CLAUDE.md の
// 「触る機会があるファイルから段階的に移行する」方針、および .claude/skills/subagent-driven-development/
// code-quality-reviewer-prompt.md の「静的解析はここで実行済み・グリーン前提」との連携。
//
// shell 経由の execSync ではなく execFileSync を使う: Windows の cmd.exe 経由だと
// 子孫プロセスのタイムアウトkillが効かずハングすることがあるため、シェルを介さず直接spawnする。
const { execFileSync } = require('child_process');

function readStdin() {
  return new Promise((resolve) => {
    let data = '';
    process.stdin.setEncoding('utf8');
    process.stdin.on('data', (chunk) => (data += chunk));
    process.stdin.on('end', () => resolve(data));
  });
}

function toSrcRelative(normalizedPath) {
  const idx = normalizedPath.indexOf('/src/');
  if (idx === -1) return null;
  return {
    srcDir: normalizedPath.slice(0, idx + 4),
    relFromSrc: normalizedPath.slice(idx + 5),
  };
}

function runEslint(srcDir, relFromSrc) {
  const eslintBin = 'node_modules/eslint/bin/eslint.js';
  const baseArgs = [eslintBin, relFromSrc, '--ext', '.js,.ts,.vue'];
  const opts = { cwd: srcDir, encoding: 'utf8', timeout: 30000, stdio: ['ignore', 'pipe', 'pipe'] };

  // フォーマット等、自動修正可能な指摘はここで直してしまう(既存コードの書式崩れでブロックしないため)。
  try {
    execFileSync(process.execPath, [...baseArgs, '--fix'], opts);
  } catch (e) {
    // --fix 後も直せないエラーが残っていると非ゼロ終了するが、正常な状態なので無視して次の再チェックに進む。
  }

  let out = '';
  try {
    out = execFileSync(process.execPath, [...baseArgs, '--format', 'json'], opts);
  } catch (e) {
    out = (e.stdout || '').toString();
    if (!out) {
      return { hasError: false, message: `[ESLint] 実行に失敗しました(設定・依存関係を確認してください): ${e.message}` };
    }
  }

  let results;
  try {
    results = JSON.parse(out);
  } catch (e) {
    return { hasError: false, message: null };
  }

  const errorLines = [];
  const warnLines = [];
  for (const file of results) {
    for (const msg of file.messages) {
      const line = `${relFromSrc}:${msg.line}:${msg.column} [${msg.ruleId || 'unknown'}] ${msg.message}`;
      if (msg.severity === 2) errorLines.push(line);
      else warnLines.push(line);
    }
  }

  if (errorLines.length === 0 && warnLines.length === 0) return { hasError: false, message: null };

  let message = '';
  if (errorLines.length) {
    message += `[ESLint] 修正が必要なエラーが見つかりました:\n${errorLines.join('\n')}\n`;
  }
  if (warnLines.length) {
    message += `[ESLint] 警告(ブロックしません、参考情報):\n${warnLines.join('\n')}\n`;
  }
  return { hasError: errorLines.length > 0, message };
}

function containerRunning(containerName) {
  try {
    const out = execFileSync('docker', ['inspect', '-f', '{{.State.Running}}', containerName], {
      encoding: 'utf8',
      timeout: 10000,
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    return out.trim() === 'true';
  } catch (e) {
    return false;
  }
}

function runPhpChecks(relFromSrc) {
  const containerName = 'trainingmemoapp-app-1';
  if (!containerRunning(containerName)) {
    return { hasError: false, message: `[static-analysis] ${containerName} コンテナが起動していないため PHP の静的解析をスキップしました。` };
  }

  const containerPath = `/var/www/html/${relFromSrc}`;
  const messages = [];
  let hasError = false;

  // Pint はフォーマッタなので --test でブロックせず、その場で自動整形する(既存コードの書式崩れでブロックしないため)。
  try {
    const out = execFileSync('docker', ['exec', containerName, 'vendor/bin/pint', containerPath], {
      encoding: 'utf8',
      timeout: 30000,
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    if (/FIXED/.test(out)) {
      messages.push(`[Pint] コードスタイルを自動整形しました:\n${out}`);
    }
  } catch (e) {
    // Pint 自体の実行失敗(構文エラー等)はブロック対象にする。
    hasError = true;
    messages.push(`[Pint] 実行に失敗しました:\n${(e.stdout || e.message || '').toString()}`);
  }

  // PHPStan(Larastan)は hook からは意図的に外している。Windows + Docker Desktop の
  // bind mount 越しだと単一ファイル解析でも ~170秒かかることを実測で確認済みで、
  // 編集のたびにこれを待たされるのは非現実的。代わりにタスク完了時のコード品質レビュー前に
  // `docker exec trainingmemoapp-app-1 composer stan` を明示的に一度実行する運用とする
  // (.claude/skills/subagent-driven-development/code-quality-reviewer-prompt.md 参照)。

  return { hasError, message: messages.length ? messages.join('\n\n') : null };
}

async function main() {
  let raw = await readStdin();
  // PowerShell 経由でシェル実行される場合、stdin が UTF-8 BOM 付きで渡ってくることがあるため除去する。
  if (raw.charCodeAt(0) === 0xfeff) raw = raw.slice(1);

  let data;
  try {
    data = JSON.parse(raw);
  } catch (e) {
    process.exit(0);
  }

  const filePath = data.tool_input && (data.tool_input.file_path || data.tool_input.filePath);
  if (!filePath) process.exit(0);

  const normalized = filePath.replace(/\\/g, '/');
  const parsed = toSrcRelative(normalized);
  if (!parsed) process.exit(0);
  const { srcDir, relFromSrc } = parsed;

  let result = { hasError: false, message: null };
  if (/^resources\/js\/.*\.(vue|ts|js)$/.test(relFromSrc)) {
    result = runEslint(srcDir, relFromSrc);
  } else if (/^app\/.*\.php$/.test(relFromSrc)) {
    result = runPhpChecks(relFromSrc);
  } else {
    process.exit(0);
  }

  if (!result.message) process.exit(0);

  if (result.hasError) {
    console.log(JSON.stringify({ decision: 'block', reason: result.message }));
  } else {
    console.log(
      JSON.stringify({
        hookSpecificOutput: { hookEventName: 'PostToolUse', additionalContext: result.message },
      })
    );
  }
  process.exit(0);
}

main();
