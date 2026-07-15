AI 長期記憶與專案約定 (AI Long-Term Memory & Project Conventions)

本文件是本倉庫 AI 代理人的「長期記憶與專案約定中心」。每一次與 AI 開始新對話時，AI 必須首先讀取本檔案與 AGENTS.md，以恢復對當前專案、技術棧、用戶偏好與持久約定的全面認知。

1. 專案基礎設定 (Project Context)

本節記錄當前專案的根本設定，請根據實際情況進行動態更新：

應用名稱 (App Name): Cloudflare Smart Cache

應用類型 (App Type): WordPress 插件 + Cloudflare 邊緣緩存解決方案

開發者 (Developer): LoveDoLove

授權 (License): MIT

目前版本 (Version): 2.3.2

PHP 版本: 7.4+ (WordPress 5.0+ 測試至 6.4)

前端技術棧 (Frontend): WordPress Admin UI (原生 PHP + HTML 表格) / VitePress (文檔網站)

後端技術棧 (Backend): PHP (WordPress 插件) + Cloudflare API (REST)

主權部署環境 (Deployment): 自托管 WordPress / GitHub Pages (文檔網站)

GitHub: https://github.com/LoveDoLove/cloudflare-smart-cache

程式碼統計 (截至 v2.3.2):
- PHP 總行數: 2,558 行 (4 個檔案: 81 + 913 + 1,499 + 65)
- 函數總數: 69 個 (2 生命週期 + 49 核心 + 15 管理員 + 1 卸載)
- Cloudflare API 端點: 13 個 (經由 cf_smart_cache_http_request())
- Transient Keys: 12 個
- Hooks: 21 add_action + 3 do_action + 3 apply_filters

2. 使用者偏好與互動慣例 (User Preferences)

溝通語言：預設使用 繁體中文 (Traditional Chinese) 進行所有對話、架構解釋與日誌記錄（除程式碼註解、變數命名與技術文檔採用英文）。

程式碼風格：
- 追求極簡與健壯性，嚴禁過度設計
- 優先使用 WordPress 原生函數與 WP 編碼標準
- 使用型別安全與現代 PHP 語法
- 必須符合內置技能包 karpathy-guidelines 的編碼行為準則
- 輸入驗證 (sanitize) + 輸出轉義 (escape) — 所有用戶輸入須 sanitize_text_field()，所有輸出須 esc_html/esc_url/wp_kses
- Nonce 驗證 — 所有表單和 URL 查詢需驗證 WP Nonce
- Capability Checks — Admin 功能需檢查 current_user_can('manage_options')
- 資料庫操作使用 $wpdb->prepare() 或 WP 查詢函數預防 SQL 注入
- 生產環境嚴禁直接印出錯誤訊息
- 所有文本均需支援 i18n 翻譯 (__(), _e(), _x())

專案慣例：
- API Token 絕不存儲在程式碼中
- 使用 Transients（而非 Options）儲存短期快取資料，設定合理 TTL
- 所有 wp_remote_* 呼叫須經由 cf_smart_cache_http_request() 包裝（含重試、退避、錯誤處理）
- HTTP 請求設定 15 秒 timeout，最多重試 3 次
- 禁止在程式碼/文檔中使用 emoji
- 重視向後相容性
- 新功能須有完整文檔說明

人機協作 (HITL) 偏好：
- 在涉及「破壞性寫入資料庫」、「線上環境部署」、「敏感金鑰修改」等操作前，AI 必須暫停並明確徵求使用者批准

3. 記憶同步與更新協議 (Memory Sync Protocol)

為了確保 AI 的記憶在跨對話中永不丟失且持續演進，AI 助手必須遵循以下同步機制：

3.1 每日日誌機制 (Daily Logs YYYY-MM-DD.md)

每次對話結束前，AI 必須將當前的關鍵決策、面臨的問題與下一步計劃，摘要寫入 memory/YYYY-MM-DD.md（以當前日期命名）。

日誌格式標準：

# 每日工作日誌: YYYY-MM-DD
* **今日進度**: [簡述完成了哪些功能/修復了哪些 Bug]
* **關鍵決策**: [例如切換了某個 API、更新了某個 Schema]
* **遭遇阻礙**: [遇到的技術難題與解決路徑]
* **明日計劃**: [待續的具體工作事項]


3.2 任務追蹤機制 (tasks.md)

所有的跨對話待辦事項必須維護在 memory/tasks.md 中。

任務分為三個看板狀態：[ ] Backlog（待辦）、[>] In Progress（進行中）、[x] Completed（已完成）。

當 AI 助手完成一項任務時，必須同步更新 memory/tasks.md，並在日誌中註記。

4. 持久技術約定 (Persistent Rules)

Cloudflare API 規範：
- HTTP Methods：全部使用 HTTPS（wp_remote_get/post/put/delete）
- Authentication：優先使用 Bearer Token，Email + API Key 作為備選方案
- Error Handling：使用 cf_smart_cache_validate_api_response() 驗證 JSON 回應
- Rate Limiting：滑動時窗 (1000 req/5min) + Token Bucket + Exponential Backoff with Jitter
- 回應處理：檢查 body['success'] 欄位，處理 body['errors'] 陣列

安全優先原則：所有新開發的端點（Endpoints）或微服務，必須在核心邏輯外圍包裹安全認證層，貫徹 AGENTS.md 中的「受控副官防禦」。

零退化 CI/CD 承諾：凡是有新的重大業務邏輯變更，必須同步在 tests/ 下建立對應的斷言測試，以利後續自動化品質飛輪（AgentOps）的集成。

技能包（Agent Skills）優先：解決特定領域問題時（例如：SEO 審計、性能優化、程式碼重構），先檢索本地 .agents/skills/，優先調用已有技能。

已知 API 限制：
- edge_cache_ttl=0 (Respect Existing Headers) 在 Free plan 不接受，最低 7200s
- Token /token/verify 不回傳 scope 列表 -> 採 fail-and-tell
- Partner/Reseller 的 plan.id 可能是 UUID，需 fallback 到 plan.name
