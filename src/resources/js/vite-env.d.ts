// import.meta.env　対策

/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_APP_API_LOGIN_URL : string,
  readonly  VITE_APP_API_REGISTER_URL:string,
  readonly VITE_ASANCTUM_STATEFUL_DOMAINS:string,
  readonly VITE_APP_TITLE: string
  // その他の環境変数...
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}