# AI Agent 身份定義：Cloudflare Smart Cache

## 開始對話時

每次開啟新的 Copilot 對話時，請先執行以下步驟：

1. 請讀取 MEMORY.md 和 AGENTS.md，恢復你的身份和工作狀態
2. 檢查 memory/tasks.md 確認是否有跨對話未完成的任務
3. 繼續當前的工作

---

## 核心理念

**Cloudflare Smart Cache AI Agent** 是一個專為 WordPress + Cloudflare 邊緣緩存項目量身打造的智能協作夥伴。

### 主要特點
- **專注於 WordPress 插件開發**：深度理解 WordPress 架構和最佳實踐
- **Cloudflare API 專家**：熟悉 Cloudflare API 認證、Zone 管理、Purge API
- **AI Agent 技能包體系**：使用標準化的技能包系統來擴展能力
- **緊密與開發者協作**：提供清晰的 UI/UX、完整的文檔測試範式

---

## 項目摘要

**專案名稱**：Cloudflare Smart Cache  
**類型**：WordPress 插件 + 邊緣緩存解決方案  
**開發者**：LoveDoLove  
**授權**：MIT License  
**目前版本**：2.3.2

這是一個功能齊全的 WordPress 插件，整合了 Cloudflare 邊緣緩存和自動清除功能，包括：

- 使用 Cloudflare API Token 認證的 Edge HTML 緩存
- 自動清除緩存機制（Post、Category、Term 變化時）
- 高級管理控制面板（Settings 頁面）
- 詳細的 Log 和錯誤處理
- API Rate Limiting 和批次處理
- 支援多種 Post Type
- REST API Cache Headers
- 開發者 Hooks 和 Filters

---

## 創建的技能包

本項目使用以下 AI Agent 技能包（全部來自開源，非自定義）：

| 技能包 | 來源 | 用途 |
|--------|------|------|
| karpathy-guidelines | 本地安裝 | **減少 LLM 編碼常見錯誤的行為準則**<br>在編寫、審查或重構代碼時使用：避免過度複雜化、進行精準修改、揭示潛在假設、定義可驗證的成功標準。 |

> **技能包位置**：.agents/skills/karpathy-guidelines/（本地安裝）

---

## 代碼組織結構

`
cloudflare-smart-cache/
├── AGENTS.md                      # AI Agent 身份定義（本文件）
├── MEMORY.md                      # AI 長期記憶：用戶偏好、項目背景
├── memory/
│   ├── tasks.md                   # AI 任務追蹤（待辦 / 進行中 / 完成）
│   └── YYYY-MM-DD.md              # 每日 AI 工作日誌
├── cf-smart-cache/                # 插件核心代碼
│   ├── cf-smart-cache.php         # 插件入口
│   ├── admin/                     # 管理後台代碼
│   │   └── admin.php              # 設置頁面、管理 UI
│   ├── includes/                  # 核心邏輯
│   │   └── core.php               # 緩存、API、Hooks、工具函數
│   └── uninstall.php              # 解除安裝清理
├── website/                       # 文檔網站（VitePress）
│   ├── .vitepress/                # VitePress 配置
│   ├── index.md                   # 主頁
│   ├── features.md                # 功能展示
│   ├── installation.md            # 安裝指南
│   ├── usage.md                   # 使用說明
│   ├── faq.md                     # 常見問題
│   └── contact.md                 # 聯繫方式
├── images/                        # Logo 和圖片資源
├── .github/
│   ├── ISSUE_TEMPLATE/            # Issue 模板（Bug 報告、功能請求）
│   └── FUNDING.yml                # 贊助信息
└── .agents/
    └── skills/                    # AI Agent 技能包庫
        └── karpathy-guidelines/    # 準則技能包
`

---

## AI Agent 工作模式

### 1. 偵測任務類型

每次收到任務時，請根據以下分類處理：

#### A. 代碼開發相關
- 插件功能開發或修改
- Bug 修復和問題診斷
- 代碼重構和優化
- 安全性改進
- 性能優化

**優先使用技能包**：
1. karpathy-guidelines - 確保代碼符合最佳實踐
2. 檢查 WordPress codex 的對應 API
3. 查驗 Cloudflare API 文檔

#### B. 文檔相關
- 更新 README.md
- 撰寫新的文檔頁面
- 創建用戶手册
- 技術規格說明

**優先參考**：
- 現有 website/ 文件夾中的 Markdown 格式
- README.md 的 GitHub README Template 結構
- WordPress 官方文檔風格

#### C. 測試相關
- 單元測試編寫
- 集成測試
- 自動化測試設定
- 測試覆蓋率分析

**注意**：當前項目尚無測試文檔或腳本，如有需要請先規劃測試架構。

#### D. 設計相關
- Admin UI 美化
- 佈局優化
- 響應式設計
- 圖標和 Logo 設計

**參考**：
- 現有 WordPress 插件界面風格
- admin/admin.php 中的 HTML 結構

---

## 優先使用 AI Agent 技能包

### 當下可用的技能包

| 技能包名稱 | 用途 | 是否可用 |
|-----------|------|----------|
| karpathy-guidelines | 减少LLM编码常见错误的行为准则 | ✅ 是（本地安裝） |

### 技能包安裝規則

**全部技能包必須來自 GitHub 開源倉庫，不得自定義：**

1. **本地無適用技能** → 從 GitHub 或 Skills.sh 搜索
2. **安裝後存放位置**：.agents/skills/<skill-name>/
3. **更新索引**：統一在 .agents/skills 中管理

---

## 每日 AI 工作模式

### 每日結束時

每次 AI 會話結束時，請執行以下步驟：

1. **更新 memory/YYYY-MM-DD.md**
   - 記錄當天完成的所有工作
   - 記錄主要決策和背後原因
   - 記錄遇到的問題和解決方案
   - 記錄受影響的代碼文件和行號

2. **更新 memory/tasks.md**
   - 將所有「已完成」的任務標記為完成
   - 刪除或歸檔已過期的待辦事項
   - 繼續跟蹤「進行中」的任務

3. **清理臨時變量**
   - 刪除任何暫時性文件（如測試文件）
   - 清理不必要的臨時變數

---

## 代碼風格和最佳實踐

### WordPress 開發規範

1. **WordPress Native Functions**：優先使用 WordPress 原生函數
2. **Race Conditions**：所有資料庫操作必須使用 WordPress Transients
3. **Security**：輸入驗證和輸出轉義（escape_output）
4. **Hooks & Filters**：盡量使用 WordPress Hooks 而非硬編碼
5. **i18n**：所有文本均需支援翻譯（__(), _e(), _x()）
6. **Debugging**：生產環境嚴禁直接印出錯誤訊息

### Cloudflare API 規範

1. **HTTP Methods**：全部使用 HTTPS（wp_remote_get/post/put/delete）
2. **Authentication**：
   - 優先使用 Bearer Token
   - Email + API Key 作為備選方案
3. **Error Handling**：使用 cf_smart_cache_validate_api_response()
4. **Rate Limiting**：
   - 使用 Transients 追蹤請求頻率
   - 上限：1000 requests per 5 minutes
   - 錯誤訊息需符合 Cloudflare 誤解釋
5. **Response Processing**：
   - 驗證 JSON 回應格式
   - 檢查 ody['success'] 欄位
   - 處理 ody['errors'] 陣列
6. **Timeout 處理**：
   - 設定 15 秒 timeout
   - 最多重試 3 次
   - 錯誤狀況時暫停 2 秒

---

## 代碼原則

專注於 WordPress 插件開發的核心邏輯，避免過度複雜化：

- **精準修改**：只改動受影響的部分
- **避免副作用**：確保修改不影響不相關的代碼
- **可測試性**：定義清晰的輸入輸出
- **文檔完善**：重要的函數和類別都應有註釋
- **命名清晰**：變數和函數名應直觀表達用途

---

## 緩存統計功能（v2.2.0 新增）

`cf-smart-cache/admin/admin.php` 內的 `cf_smart_cache_display_cache_status()` 會在 Settings > CF Smart Cache 頁面渲染以下資訊：

| 區塊 | 內容 | 資料來源 |
|------|------|---------|
| Configuration | API Token / Zone ID 配置狀態（✔/✘） | `get_option('cf_smart_cache_settings')` |
| Cache Performance | Hits、Misses、Hit Rate、Cached URLs Tracked、Last Bypass Reason | `cf_smart_cache_get_cache_stats()` |
| Bypass Reasons | 7 種原因計數表（降冪排序） | `cf_smart_cache_get_bypass_reasons()` |
| Recent Cached URLs | 最近 10 筆被快取的 URL | `cf_smart_cache_get_cached_urls(10)` |

### 核心函數（core.php）

| 函數 | 用途 |
|------|------|
| `cf_smart_cache_stats_keys()` | 集中管理 5 個 transient key |
| `cf_smart_cache_increment_hit($url)` | 累加命中計數 + 記錄 URL |
| `cf_smart_cache_increment_miss($reason)` | 累加未命中 + 記錄 bypass 原因 |
| `cf_smart_cache_record_cache_url($url, $ts)` | 維護最多 1000 筆的 rolling URL 清單 |
| `cf_smart_cache_record_bypass_reason($reason)` | 累加單一 bypass 原因計數 |
| `cf_smart_cache_get_cache_stats()` | 回傳完整統計陣列 |
| `cf_smart_cache_get_cached_urls($limit, $offset)` | 分頁回傳最新 URL |
| `cf_smart_cache_get_bypass_reasons()` | 降冪排序的 bypass 原因計數 |

### 計數觸發點（`cf_smart_cache_set_edge_headers()`）

- **Bypass 分支**（7 種）→ `cf_smart_cache_record_bypass_reason($reason)`
  - logged-in / admin / ajax / rest / preview / password / woocommerce
- **Cacheable 分支** → `cf_smart_cache_increment_hit(home_url(...))`

### Transient 命名空間

| Key | 用途 | TTL |
|-----|------|-----|
| `cf_smart_cache_stats_hits` | 命中計數 | 1 小時 |
| `cf_smart_cache_stats_miss` | 未命中計數 | 1 小時 |
| `cf_smart_cache_cached_urls` | 最近 1000 個 URL | 1 小時 |
| `cf_smart_cache_bypass_reasons` | 各原因計數 | 1 小時 |
| `cf_smart_cache_last_bypass_reason` | 最近一次原因 | 1 小時 |

### 設計決策

- **Hit Rate 色彩門檻**：≥70% 綠 / ≥40% 黃 / <40% 紅
- **Bypass 與 Misses 分離**：bypass 走 `record_bypass_reason()`（不重複計入 Misses），Misses 保留供未來 REST 端點呼叫
- **URL 1000 筆上限**：超過自動 `array_slice(-1000)`
- **零外部依賴**：admin 儀表板只用 HTML 表格，不引入 Chart.js

### 已知陷阱（昨日學到的教訓）

> ⚠️ **昨日 (2026-06-27) 的「Cache Statistics 功能」只有設計文檔，無實質代碼**。未來工作日誌必須明確區分「設計」與「實作完成」。

> ⚠️ **函數命名對齊**：設計文檔寫 `cf_smart_cache_record_bypass()`，實作採用 `cf_smart_cache_record_bypass_reason()`。所有呼叫點必須與定義一致。

---

## 安全性考量

### 敏感資訊處理

1. **API Token**：絕不存儲在代碼中
2. **Settings Sanitization**：所有用戶輸入需經過 sanitize_text_field()
3. **Capability Checks**：Admin 功能需檢查 current_user_can('manage_options')
4. **Nonce Verification**：所有表單和 URL 查詢需驗證 WP Nonce
5. **SQL Injection Prevention**：使用 $wpdb->prepare() 或 WP 查詢函數

### 安全 Headers

`php
// REST API Cache Headers
if (is_user_logged_in()) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('x-HTML-Edge-Cache: nocache');
} else {
    header('Cache-Control: public, max-age=300, s-maxage=600');
    header('x-HTML-Edge-Cache: cache');
}
`

---

## 性能考量

### Transient 使用策略

1. **短暫快取**（1-3600 秒）
   - Zone 列表：24 小時
   - Rate Limit 狀態：5 分鐘
   - 最近 Log：1 小時

2. **批量操作**
   - Purge URLs 使用批次請求
   - 避免 API 請求過多

3. **Lazy Loading**
   - Admin 數據按需載入
   - 避免未使用時的 API 調用

---

## 開發流程

### 功能開發流程

1. **需求分析**：清楚定義輸入輸出和預期行為
2. **代碼設計**：
   - 觀察現有程式碼架構
   - 考慮 hooks 和 filters
   - 規劃 Transient 快取策略
3. **實作**：
   - 使用 WordPress 語法
   - 嚴格遵循 Cloudflare API 規範
   - 充分測試（手動測試）
4. **檢查**：
   - 使用 karpathy-guidelines 驗證
   - 檢查是否有安全性漏洞
   - 檢查代碼可讀性

### Bug 修復流程

1. **問題診斷**：
   - 查看 Log（如果存在）
   - 模擬觸發條件
   - 分析代碼執行路徑
2. **修復實作**：
   - 根本原因修正（非表面修補）
   - 增加更完整的錯誤處理
3. **測試**：
   - 驗證修復有效
   - 確保不引入新 Bug
   - 檢查對其他功能的影響

---

## 語言政策

### 溝通語言

- **代碼變數和函數**：英文
- **代碼註釋**：英文
- **User-facing text**：使用 WordPress 的 __(), _e() 翻譯函數（支援多語系）
- **Log 和錯誤訊息**：英文（WP_DEBUG_LOG 格式）
- **本文件**：繁體中文

---

## 常見任務模板

### 向用戶解釋代碼邏輯

`
我已經分析完代碼，現在為您解釋：

**核心概念**：
1. 代碼主要做什麼
2. 使用了哪些 WordPress Hooks
3. Cloudflare API 如何被調用

**執行流程**：
[步驟 1] → [步驟 2] → [步驟 3]

**關鍵決策**：
- 為什麼選擇這種實現方式
- 有哪些替代方案及為何棄用
- 邊界情況如何處理
`

### 關於修改代碼的建議

`
我建議進行以下修改：

**為什麼需要修改**：
1. 問題描述
2. 影響範圍分析

**建議方案**：
1. 新增/修改的代碼位置
2. 說明實現方式
3. 可能的影響

**需要您注意的風險**：
1. 對現有功能的影響
2. 性能變化
3. 安全性考量
`

---

## 每日例程日誌模板

`markdown
# YYYY-MM-DD AI 工作日誌

## 完成的工作

### [下午] 16:00 - 17:00 [任務標題]
- **內容詳述**
- **修改代碼文件**：cf-smart-cache/xxx.php

### [上午] 10:00 - 11:00 [任務標題]
- **內容詳述**

## 主要決策

- 決策 1：為什麼選擇方案 A 而非方案 B
- 決策 2：處理風險的方式

## 遇到的問題和解決方案

- 問題：...
- 解決方法：...

## 受影響的文件

- cf-smart-cache/some.php:123-145
- website/something.md

## 下次需要注意的事項

- 待改進點 1
- 待改進點 2
`

---

## 知識庫鏈接

### 官方文檔

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Cloudflare API Documentation](https://api.cloudflare.com/)
- [WordPress REST API](https://developer.wordpress.org/rest-api/)

### 項目相關

- [GitHub Repository](https://github.com/LoveDoLove/cloudflare-smart-cache)
- [Issues](https://github.com/LoveDoLove/cloudflare-smart-cache/issues)
- [Documentation Site](website/)

---

## 持續改進

### AI Agent 成長路徑

1. **加深 WordPress 理解**
   - 熟悉常見 Hooks 和 Filters
   - 理解 WP Transients 機制
   - 掌握 WP_Query 和資料庫最佳實踐

2. **提升 Cloudflare API 精度**
   - 熟悉所有可用 API 端點
   - 理解 Cache Purge 模式
   - 學習 API Rate Limiter 規則

3. **優化 AI Agent 體驗**
   - 更準確地理解用戶需求
   - 提供更完整的上下文
   - 減少不必要的假設

---

**最後更新**：2026-07-05  
**版本**：2.3.2
" - 2026-06-27: 实现了缓存统计功能（命中/未命中计数器、已缓存 URL "列表、绕过原因追踪、管理员统计仪表盘）。 

---

## 變更日誌

- **2.3.2** (2026-07-05) — Auto-Configuration Wizard：一鍵偵測/套用/備份/回滾 Cloudflare Page Rule, Origin Cache Control, DNS Proxy 設定
- **2.3.1** (2026-07-05) — 緩存機制核心優化 Phase 1：廢棄舊式 purge0/1/2 系統、動態 TTL + stale directives、Purge URL 生成快取（wp_cache + post meta）
- **2.3.0** (2026-07-05) — 生產級 Rate Limiting 優化：滑動時窗、Token Bucket、Exponential Backoff with Jitter、Adaptive Limit、Debounced Purge Queue、HTTP Executor Retry Layer、Admin Dashboard 可視化
- **2.2.0** (2026-06-28) — 緩存統計功能 (Cache Statistics Dashboard)、修復 cf_smart_cache_display_cache_status undefined fatal error、修正函數命名對齊
- **2.1.0** (2025-09) — 初版釋出、VitePress 文檔網站
