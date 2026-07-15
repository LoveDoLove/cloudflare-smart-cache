# AI 長期記憶與專案約定 (AI Long-Term Memory & Project Conventions)

本文件是本倉庫 AI 代理人的「長期記憶與專案約定中心」。每一次與 AI 開始新對話時，AI 必須首先讀取本檔案與 AGENTS.md，以恢復對當前專案、技術棧、用戶偏好與持久約定的全面認知。

## 1. 專案基礎設定 (Project Context)

| 項目 | 內容 |
|------|------|
| 應用名稱 | Cloudflare Smart Cache |
| 應用類型 | WordPress 插件 + Cloudflare 邊緣緩存解決方案 |
| 開發者 | LoveDoLove |
| 授權 | MIT |
| 目前版本 | 2.4.0 |
| PHP 版本 | 7.4+ (WordPress 5.0+) |
| GitHub | https://github.com/LoveDoLove/cloudflare-smart-cache |

### 程式碼統計 (v2.4.0)

- **PHP 總行數**: 2,033 行（11 個活躍檔案，不含舊版 admin.php 846 行）
- **類**: 6 個（API Client / Cache Manager / Purge Manager / Stats Manager / Rate Limiter + Admin）
- **包裝函數**: 54 個（core.php 中的薄包裝，保持向後兼容）
- **架構**: 從單體 core.php（1499行）+ admin.php（913行）遷移至 OOP 類結構
- **AJAX Endpoints**: 5 個（purge_all / purge_homepage / fetch_zones / auto_config / save_settings）
- **JS**: inline vanilla XMLHttpRequest + 24 行 admin.js（jQuery 增強，可選）
- **CSS**: 14 行 admin.css

## 2. 使用者偏好與互動慣例 (User Preferences)

**溝通語言**: 預設使用繁體中文。

**程式碼風格**:
- 追求極簡與健壯性，嚴禁過度設計
- 優先使用 WordPress 原生函數與 WP 編碼標準
- 所有用戶輸入須 sanitize，所有輸出須 escape
- Nonce 驗證所有表單和 AJAX 請求
- Admin 功能需檢查 `current_user_can('manage_options')`
- 資料庫操作使用 `$wpdb->prepare()` 預防 SQL 注入
- 所有文本支援 i18n（`__()`, `_e()`, `_x()`）

**UI 設計原則**:
- 所有操作使用 inline onclick + vanilla JS（零依賴 admin.js / jQuery）
- 按鈕使用 `type="button"` 而非 `type="submit"`（防止意外表單提交）
- Tab 切換使用 CSS display 控制 + inline JS（無 AJAX 加載）
- 設定保存 / Purge / Auto-Config 全部使用 AJAX 無刷新

**專案慣例**:
- API Token 絕不存儲在程式碼中
- 使用 Transients 儲存短期快取資料
- 重視向後相容性，舊函數保留為薄包裝

## 3. 記憶同步與更新協議 (Memory Sync Protocol)

### 3.1 每日日誌機制

每次對話結束前，AI 必須將當前的關鍵決策、面臨的問題與下一步計劃摘要寫入 `memory/YYYY-MM-DD.md`。

### 3.2 任務追蹤機制

所有跨對話待辦事項維護在 `memory/tasks.md` 中。任務分為三個狀態：`[ ]` Backlog、`[>]` In Progress、`[x]` Completed。

## 4. 持久技術約定 (Persistent Rules)

**Cloudflare API 規範**:
- HTTP Methods：全部使用 HTTPS
- Authentication：優先使用 Bearer Token
- Error Handling：使用 `CF_Smart_Cache_API::validate_api_response()`
- Rate Limiting：滑動時窗 + Token Bucket + Exponential Backoff

**已知 API 限制**:
- `edge_cache_ttl=0` 在 Free plan 不接受，最低 7200s
- Token `/token/verify` 不回傳 scope 列表 → 採 fail-and-tell
- Partner/Reseller 的 `plan.id` 可能是 UUID，需 fallback 到 `plan.name`
