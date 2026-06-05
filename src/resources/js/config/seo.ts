const COMMON_KEYWORDS = "筋トレ, ジムトレ, トレーニング, 全てのトレーニング";

function mergeKeywords(base: string) {
    return `${base}, ${COMMON_KEYWORDS}`;
}

export const SEO = {
    DEFAULT: {
        title: "トレーニング記録 | 毎日の運動を簡単に管理・メモできるサービス",
        description:
            "トレーニング記録サービス「トレメモ」では、日々の運動・トレーニング内容を手軽にメモ・管理できます。日付ごとの記録でモチベーション維持に最適！スマホ・PC対応、無料で使えます。",
        keywords: mergeKeywords(
            "トレーニング記録, 運動メモ, 筋トレ管理, ワークアウトログ, トレーニング日記, フィットネス管理, ジム"
        ),
        robots: "index, follow",
    },
    home: {
        title: "トレメモ | トレーニング記録サービス",
        description:
            "トレメモは、あなたの日々のトレーニングを簡単に記録・管理できる無料サービスです。運動習慣をサポート！",
        keywords: mergeKeywords(
            "トレメモ, トレーニング記録, 運動管理, フィットネスメモ"
        ),
        robots: "index, follow",
    },
    login: {
        title: "ログイン | トレメモ",
        description:
            "トレメモにログインして、あなたのトレーニング記録を管理しましょう。簡単登録・無料で使えます。",
        keywords: mergeKeywords("トレメモ, ログイン, トレーニング記録"),
        robots: "noindex, nofollow",
    },
    RedirectAuthGoogle: {
        title: "Google認証リダイレクト | トレメモ",
        description:
            "Googleアカウントを使ってトレメモに簡単ログイン・登録できます。",
        keywords: mergeKeywords("トレメモ, Googleログイン, 簡単登録"),
        robots: "noindex, nofollow",
    },
    register: {
        title: "新規登録 | トレメモ",
        description:
            "トレメモに無料登録して、毎日のトレーニング記録をスタートしましょう！",
        keywords: mergeKeywords(
            "トレメモ, 新規登録, 無料会員登録, トレーニング管理"
        ),
        robots: "noindex, nofollow",
    },
    googleRegister: {
        title: "Google連携登録 | トレメモ",
        description:
            "Googleアカウントでトレメモに登録できます。わずか数秒で簡単スタート！",
        keywords: mergeKeywords("トレメモ, Google連携, 簡単登録"),
        robots: "noindex, nofollow",
    },
    PasswordForget: {
        title: "パスワードをお忘れですか？ | トレメモ",
        description: "パスワードをお忘れの場合はこちらから再設定できます。",
        keywords: mergeKeywords(
            "トレメモ, パスワードリセット, パスワード忘れた"
        ),
        robots: "noindex, nofollow",
    },
    ResetPassword: {
        title: "パスワード再設定 | トレメモ",
        description:
            "新しいパスワードを設定して、トレメモを再びご利用いただけます。",
        keywords: mergeKeywords("トレメモ, パスワード再設定"),
        robots: "noindex, nofollow",
    },
    Inquiry: {
        title: "お問い合わせ | トレメモ",
        description:
            "トレメモに関するご質問やご相談はこちらからお問い合わせください。",
        keywords: mergeKeywords("トレメモ, お問い合わせ, サポート"),
        robots: "index, follow",
    },
    selectMenu: {
        title: "メニュー選択 | トレメモ",
        description:
            "トレーニング種目を選択して、あなたの運動記録を充実させましょう。",
        keywords: mergeKeywords(
            "トレメモ, メニュー選択, 種目追加, トレーニング種目"
        ),
        robots: "index, follow",
    },
    record: {
        title: "トレーニング記録詳細 | トレメモ",
        description:
            "トレーニング記録の詳細ページです。日付ごとの運動内容を確認・編集できます。",
        keywords: mergeKeywords(
            "トレメモ, 記録詳細, トレーニングログ, 運動履歴"
        ),
        robots: "index, follow",
    },
    addMenu: {
        title: "メニュー追加 | トレメモ",
        description:
            "新しいトレーニング種目を追加して、記録をさらに充実させましょう。",
        keywords: mergeKeywords("トレメモ, メニュー追加, トレーニング種目追加"),
        robots: "index, follow",
    },
    userRecordRanking: {
        title: "ユーザー記録ランキング | トレメモ",
        description:
            "トレーニング記録ランキングをチェックして、他のユーザーと成果を比較してみましょう。",
        keywords: mergeKeywords(
            "トレメモ, 記録ランキング, トレーニングランキング"
        ),
        robots: "index, follow",
    },
    nothing: {
        title: "ページが見つかりません | トレメモ",
        description:
            "指定されたページが見つかりませんでした。トップページに戻るか、メニューから選んでください。",
        keywords: mergeKeywords("トレメモ, ページが見つかりません, 404エラー"),
        robots: "noindex, nofollow",
    },
};
