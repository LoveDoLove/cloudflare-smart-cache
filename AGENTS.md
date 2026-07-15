# 通用代理人工程架構與行為規範 (Universal Agentic Architecture & Identity Specification)

本文件是本倉庫 AI 代理人（Agent）的「身份定義與行為規範行為準則」，也是每次對話的至高入口。

## 1. 倉庫核心結構

```
%USERPROFILE%
├── .agents/skills/           # AI Agent 技能包庫

projects/cloudflare-smart-cache/
├── AGENTS.md                 # 身份定義與行為規範
├── MEMORY.md                 # 長期記憶與專案約定
├── memory/
│   ├── tasks.md              # 任務追蹤
│   └── YYYY-MM-DD.md         # 每日工作日誌
├── docs/
│   └── developer-hooks.md    # 開發者 Hooks/Filter 文檔
├── tests/                    # PHPUnit 測試框架（10 tests）
└── .opencode/plans/          # 執行計劃文件
```

## 2. 已安裝技能包

- **karpathy-guidelines** — 減少 LLM 編碼錯誤的行為準則
- **skillx** — 通用技能（每次任務載入）
- **brainstorming** — 創意工作前的需求探索與設計
- **systematic-debugging** — 根因分析導向的除錯流程
- **writing-plans** — 生成詳細執行計劃
- **subagent-driven-development** — 子代理並行開發工作流
- **test-master** / **test-driven-development** — 測試驅動開發
- **code-reviewer** / **requesting-code-review** — 代碼審查
- **fullstack-guardian** / **secure-code-guardian** — 安全開發
- **devops-engineer** — CI/CD / Docker / K8s 配置
- **codebase-memory** — 知識圖譜代碼查詢
- **ui-ux-pro-max** — UI/UX 設計
- **dispatching-parallel-agents** — 並行子代理調度
- **verification-before-completion** — 完成前驗證

## 3. 技能調用原則

1. 接到任務時優先檢索 `.agents/skills/` 目錄
2. 調用時宣告：「偵測到相關任務，正在加載技能包 [name]...」
3. 嚴格執行 SKILL.md 指引

## 4. 三大不妥協鐵律

1. **受控副官防禦** — JWT/ACL 系統隔離，不信 Prompt
2. **宣告式工具解耦** — MCP/JSON 介面，模型與工具分離
3. **軌跡即真理** — OpenTelemetry 標準追蹤

## 5. 當前專案完成狀態

所有 v2.5.0 計劃功能已完成：
- 單體重構為 6 個 OOP 類，54+ 包裝函數保持向後相容
- AJAX 管理介面（零 jQuery 依賴、無頁面刷新）
- 即時活動日誌（每 5 秒 AJAX 自動輪詢刷新，所有操作皆記錄）
- 邊緣 HTML 快取 + 安全標頭 + 動態 TTL
- 自動清除（文章/分類/選單/主題變更）
- 選擇性清除 by Post Type
- Cache Hit Ratio 告警
- 排程全站清除（WP-Cron 每日/每週）
- PHPUnit 測試框架（10 tests, 22 assertions）
- 開發者文檔（docs/developer-hooks.md）
- API 速率限制 + 指數退避
