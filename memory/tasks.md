# AI 任務追蹤

這個文件用於追蹤跨對話的待辦事項和進度。每次新的會話開始時，請檢查是否有未完成的任務。

---

## [待辦 - Pending]

### [項目導向任務]

#### [Phase 1] 緩存機制核心優化（高優先級）
- **狀態**：已完成
- **優先級**：高
- **完成內容**：
  1. **廢棄舊式 purge0/1/2 系統** — 移除 `cf_smart_cache_purge()/purge0/1/2/post_transition` 及 15+ 舊事件繫結；改為精簡的 `init_action()` 僅保留 edge headers + 3 個必要事件（switch_theme/edit_user_profile/wp_update_nav_menu）
  2. **動態 TTL + stale directives** — 新增 `cf_smart_cache_get_ttl()`，內容感知 TTL 表（首頁/文章/歸檔/feed），加入 `stale-while-revalidate=86400` / `stale-if-error=604800`
  3. **Purge URL 生成快取** — 雙層快取：wp_cache（per-request）+ post meta（cross-request hash-based），新增 `cf_smart_cache_purge_urls_hash()`，deleted post 自動清理 meta
- **驗證**：php -l 無語法錯誤，`grep purge0/purge1/purge2` 無殘留

#### [Phase 2] 緩存功能性增強（中優先級）
- **狀態**：待決
- **優先級**：中
- **說明**：
  1. **選擇性清除 by Post Type** — Settings 頁面 checkbox 群組，可過濾哪些 post type 觸發清除
  2. **清除事件去重與節流** — request-level static dedup，忽略 publish→publish 等無意義轉變
  3. **Cache Hit Ratio 告警** — 連續 3 小時低於 30% 時 admin notice 提示
- **預期輸出**：
  - 新的清除範圍篩選 UI
  - 事件觸發次數降低 >50%

#### [Phase 3] 緩存增強功能（低優先級）
- **狀態**：待決
- **優先級**：低
- **說明**：
  1. **Cache Warming** — 清除後自動對熱門 URL 發送 HEAD 請求
  2. **排程全站清除** — WP-Cron 每日/每週定時全站清除

#### [Phase 4] Auto-Configuration Wizard（中優先級）
- **狀態**：已完成
- **優先級**：中
- **完成內容**：
  1. **偵測層** — `cf_smart_cache_get_config_status()` 回傳完整的 Page Rule / Origin Cache Control / DNS Proxy / Backup 狀態
  2. **Page Rule 執行** — `POST/PUT /pagerules` 建立或更新規則，payload 含 `cache_level=cache_everything` + `edge_cache_ttl=0` + `explicit_cache_control=on`
  3. **Zone Settings 執行** — `PATCH /settings/explicit_cache_control → on`；`PATCH /settings/edge_cache_ttl` for rollback
  4. **DNS Proxy 執行** — 批次 `PATCH /dns_records/{id} → proxied=true`，支援 root-only 或全部策略
  5. **備份/回滾** — 最多 3 版快照（option `cf_smart_cache_config_backups`），回滾前自動再備份，ID 精確匹配還原
  6. **Admin UI** — 狀態燈號 + 可勾選開關 + 策略下拉 + 4 按鈕（Backup/Apply/Rollback）
- **影響文件**：
  - cf-smart-cache/includes/core.php（+14 個新函數，~200 行）
  - cf-smart-cache/admin/admin.php（+2 個新函數 ~130 行）
- **驗證**：php -l 兩檔皆無語法錯誤
- **版本**：提升至 2.3.2

#### [轉換] 實作 PHP 單元測試框架
- **狀態**：待決
- **優先級**：中
- **說明**：
  - 需要建立測試框架（如 PHPUnit 或 WP-CLI tests）
  - 測試 Cloudflare API 通訊
  - 測試緩存邏輯
  - 測試 Purge API 整合
- **預期輸出**：
  - tests/ 目錄結構
  - 至少 10 個單元測試案例
  - CI/CD 整合

---

### [文檔導向任務]

#### [轉換] 更新開發者文檔
- **狀態**：待決
- **優先級**：中
- **說明**：
  - 添加 API Hooks 和 Filters 詳細文檔
  - 添加自定義 Post Type 支援指南
  - 添加最佳實踐建議
- **預期輸出**：
  - website/developer-guide.md
  - 更新 website/api-hooks.md

---

## [進行中 - In Progress]

無

---

## [完成 - Completed]

### [2026-07-05] 優化 Cloudflare API 請求速率限制（v2.3.0）
- **狀態**：已完成
- **優先級**：高
- **完成內容**：
  - 滑動時窗 Governor：`cf_smart_cache_rate_governor()` — 5 分鐘滑動時窗、狀態機（normal/warning/critical/backoff）、自動調適限流（遇 429 降 10%，下限 600，1h 無 429 恢復 50）
  - Token Bucket：`cf_smart_cache_purge_bucket()` — 依 Cloudflare 方案（Free/Pro/Business/Enterprise）選擇參數，支援 burst
  - 智慧退避：`cf_smart_cache_backoff_delay()` — exponential 級數 [1,2,4,8,15] + ±20% jitter + retry-after header 感知
  - 429 Handler：`cf_smart_cache_handle_429_response()` — 調降 adapted_limit、追蹤連續 429、排程 backoff
  - HTTP Executor：`cf_smart_cache_http_request()` — 封裝所有 wp_remote_* 呼叫，3 次重試（可配置 1-5），讀 retry-after header
  - Debounced Queue：`cf_smart_cache_enqueue_purge()` + `cf_smart_cache_flush_purge_queue()` — 2 秒合併視窗、滿 100 強制 flush
  - 修改所有 Caller：batch_purge / execute_purge / purge_all_cache / fetch_zones 改用 http_request
  - Hook 端：on_status_change / on_delete_post / on_term_change 改用 enqueue_purge
  - Admin UI：Rate Limit 狀態區塊（State、Window Usage、429s、Queue）、5 個設定欄位（max/retries/adaptive/plan/batch_size）
  - 向後相容：保留 `cf_smart_cache_check_rate_limit()` wrapper
  - 版本提升至 2.3.0，更新 AGENTS.md changelog
- **影響文件**：
  - cf-smart-cache/includes/core.php（+290 行新函數、修改 6 個既有函數）
  - cf-smart-cache/admin/admin.php（+120 行：設定欄位 + 儀表板區塊）
  - cf-smart-cache/cf-smart-cache.php（版本號 + 清理新 transient）
- **驗證**：php -l 三檔皆無語法錯誤

---

### [2026-06-28] 修復 Fatal Error 並補完 Cache Statistics 功能
- **完成內容**：
  - 修復 admin.php 缺少 `cf_smart_cache_display_cache_status()` 造成的 Fatal Error
  - 在 core.php 新增 Cache Statistics 函數家族（hit/miss 計數器、URL 記錄、bypass reason、stats getter）
  - 在 `cf_smart_cache_set_edge_headers()` 每個分支掛上 bypass/hit 計數
  - 在 admin.php 完整實作 `cf_smart_cache_display_cache_status()`（API/Zone 狀態、Performance、Bypass Reasons、Recent Cached URLs）
- **影響文件**：
  - cf-smart-cache/includes/core.php（+約 130 行統計函數、修訂 set_edge_headers）
  - cf-smart-cache/admin/admin.php（補上 display_cache_status 實作，約 75 行）
- **驗證**：php -l 兩檔皆無語法錯誤
- **待辦**：下次實際在 WordPress 後台驗證頁面渲染、計數遞增、bypass reason 觸發

---

### [2026-06-27] 初始化 MEMORY.md 和 AGENTS.md
- **完成內容**：
  - 建立記憶機制文檔（MEMORY.md）
  - 建立 AI Agent 身份定義（AGENTS.md）
  - 分析完整項目結構和功能
  - 記錄核心功能和技術規範
- **影響文件**：
  - MEMORY.md（新檔案）
  - AGENTS.md（新檔案）

---

### [2025-09] 建立 VitePress 文檔網站
- **完成內容**：
  - 設立 website 目錄
  - 建立 .vitepress 配置
  - 創建主頁、特色、安裝、用法、FAQ、聯繫頁面
- **影響文件**：
  - website/ 目錄結構
  - website/*.md 文件

---

### [2025-09] 建立 Issue 模板
- **完成內容**：
  - 添加 bug report 模板
  - 添加 feature request 模板
  - 建立 FUNDING.yml
- **影響文件**：
  - .github/ISSUE_TEMPLATE/bug-report---.md
  - .github/ISSUE_TEMPLATE/feature-request---.md
  - .github/FUNDING.yml

---

## 下次會議待確認事項

### 短期（本週）
- [ ] 詢問管理員：是否有新功能需求要優先處理
- [ ] 檢查 WordPress 6.4 測試結果

### 中期（本月）
- [ ] 建立測試框架
- [ ] 優化文檔品質

### 長期（本季）
- [ ] 性能壓力測試
- [ ] 安全性審計

---

**最後更新**：2026-07-05
**下次檢查**：2026-07-06
