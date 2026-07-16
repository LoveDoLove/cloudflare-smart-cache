# 通用代理人工程架構與行為規範 (Universal Agentic Architecture & Identity Specification)

本文件是本倉庫 AI 代理人（Agent）的「身份定義與行為規範行為準則」，也是每次對話的至高入口。本文件將「神諭級全端代理人工程鐵律」與「本地技能包（Agent Skills）工作流」進行深度綁定，確保 AI 助手在解決任何工程問題時，皆能遵循高解耦、高安全、可觀測的最高標準。

## 1. 倉庫核心結構 (Repository Structure)

本倉庫採用「自適應認知與技能架構」，結構如下：

```
%USERPROFILE%
├── .agents/skills/           # AI Agent 技能包庫（可發現、可安裝、可複用）

projects/cloudflare-smart-cache/
├── AGENTS.md                 # AI Agent 身份定義與行為規範（每次對話的入口）
├── MEMORY.md                 # AI 長期記憶：用戶偏好、專案背景、持久約定
├── memory/
│   ├── tasks.md              # AI 任務追蹤（待辦 / 進行中 / 完成）
│   ├── tools-state.json      # 工具安裝狀態快照
│   └── YYYY-MM-DD.md         # 每日 AI 工作日誌
├── docs/
│   └── developer-hooks.md    # 開發者 Hooks/Filter 文檔
├── tests/                    # PHPUnit 測試框架（10 tests）
├── website/                  # VitePress 文檔網站（已部署至 Cloudflare Pages）
└── .opencode/plans/          # 執行計劃文件
```

## 2. AI Agent 技能工作流 (Skill-Pack Engine)

技能包（Agent Skill）是本倉庫的核心機制——將可複用的 AI 能力封裝為標準單元，存放在 `.agents/skills/<skill-name>/`。主入口為 SKILL.md，AI 助手在接到任務時必須遵循以下工作流：

### 2.1 技能調用工作流 (Invocation Workflow)

1. **任務檢索**：接到任何代碼、架構或優化任務時，AI 必須優先檢索本地 `.agents/skills/` 目錄，查看是否有符合的技能包。
2. **外部導入（不自行編寫）**：若本地無合適技能包，嚴禁 AI 自行憑空編寫技能包。必須優先搜尋 GitHub 開源社群或 Skills.sh 進行 Clone，並統一安裝存放至 `.agents/skills/<skill-name>/`。
3. **加載與聲明**：調用技能包時，AI 必須在對話中明確聲明：「偵測到相關任務，正在加載本地技能包 [skill-name]...」，並嚴格執行該技能包中的 SKILL.md 指引。

### 2.2 已安裝技能包索引

- **karpathy-guidelines** — 減少 LLM 編碼常見錯誤的行為準則。在編寫、審查或重構代碼時使用這些準則，可以避免過度複雜化，進行精準修改，揭示潛在假設，並定義可驗證的成功標準。
- **skillx** — 技能市場搜尋與調用。每項任務都應載入此技能。
- **brainstorming** — 創意工作前的需求探索與設計
- **systematic-debugging** — 根因分析導向的除錯流程
- **writing-plans** — 生成詳細執行計劃
- **subagent-driven-development** — 子代理並行開發工作流
- **test-master / test-driven-development** — 測試驅動開發
- **code-reviewer / requesting-code-review** — 代碼審查
- **fullstack-guardian / secure-code-guardian** — 安全開發
- **devops-engineer** — CI/CD / Docker / K8s 配置
- **codebase-memory** — 知識圖譜代碼查詢
- **ui-ux-pro-max** — UI/UX 設計智慧。提供 84 種設計風格、192 種色板、74 種字體搭配等。
- **dispatching-parallel-agents** — 並行子代理調度
- **verification-before-completion** — 完成前驗證

### 2.3 每次對話初始化流程 (Session Init Protocol)

AI 助手在每次新對話開始時，必須依次執行：

1. 讀取 `memory/tools-state.json`，取得已安裝工具的快照
2. 對每個 `status="installed"` 的項目進行 quick-verify：
   - Skills：檢查技能路徑的 SKILL.md 是否存在
   - MCPs：檢查對應 CLI 有回應，或 opencode 設定中 MCP entry 是否存在
   - Plugins：檢查 opencode.json 設定
3. 若發現實際狀態與 tools-state.json 不符（如檔案被刪除、CLI 消失），立即更新該檔案
4. 對於有 version 欄位的項目，檢查 `version_checked_at` 是否超過 7 天：
   - 是 → 執行版本檢查（如 `ctx7 --version`），更新 `latest_version` 與 `version_checked_at`
   - 若 `latest_version ≠ version` → 在回覆尾聲附註「[工具名稱] 有新版本 [latest]，可執行 .\init.ps1 查看」
5. 確認 skillx 技能已載入（設計為 every task 使用）
6. 根據使用者的任務，參照 `tools-state.json` 決定哪些技能可用並調用

### 2.4 技能調用原則

1. 接到任務時優先檢索 `.agents/skills/` 目錄
2. 調用時宣告：「偵測到相關任務，正在加載技能包 [name]...」
3. 嚴格執行 SKILL.md 指引

## 3. 代理人架構三大不妥協鐵律 (The Three Uncompromisables)

不論任務規模多小，AI 助手在執行任何工具呼叫與後端變更時，必須 100% 遵守以下三大鐵律：

```
+---------------------------------------------------------------------------------+
|                                 三大不妥協鐵律                                   |
|                                                                                 |
|  1. 受控副官防禦 (Security)   ====>  JWT/ACL 系統級隔離，嚴禁信任 Prompt 意志    |
|  2. 宣告式工具解耦 (Decoupling) ====>  使用標準化介面 (MCP/JSON)，工具與 LLM 完全分離 |
|  3. 軌跡即真理 (Observability) ====>  採用 OpenTelemetry 標準，完整追蹤決策 Spans  |
+---------------------------------------------------------------------------------+
```

### 3.1 鐵律一：受控副官防禦 (Confused Deputy Defense)

**核心原則**：永遠不要相信 Prompt 能守住系統安全。

**實踐**：AI 助手本質上只是個「信差（Messenger）」。當代表用戶調用後端工具（API、資料庫、檔案系統）時，工具執行層必須依據使用者的安全憑證（JWT / ACL）進行硬性隔離校驗。縱使 AI 遭受 Prompt 注入（Prompt Injection）被洗腦，系統層也必須直接拒絕其越權存取。

### 3.2 鐵律二：宣告式工具解耦 (Declarative Tool Decoupling)

**核心原則**：禁止將工具呼叫邏輯與 LLM 驅動程式碼硬編碼（Hardcode）在一起。

**實踐**：工具必須是以宣告式（如 Model Context Protocol 協定或 JSON Schema）定義。模型僅負責輸出調用決策與參數。這確保了當底層模型（如從 Gemini 換到 Claude）迭代時，一條工具程式碼都不需要修改。

### 3.3 鐵律三：軌跡即真理 (Trajectory is the Truth)

**核心原則**：拋棄傳統 console.log，採用標準「軌跡追蹤（Trace）」。

**實踐**：必須完整記錄每一次推理的上下文。一個標準的 Trace Span 必須依循 OpenTelemetry GenAI Semantic Conventions 標準格式輸出：

```json
{
  "trace_id": "8f3b1a2c5e7d9f0a1b2c3d4e5f6a7b8c",
  "span_id": "4a5b6c7d8e9f0a1b",
  "name": "agent_execution_loop",
  "attributes": {
    "gen_ai.system": "gemini",
    "gen_ai.request.model": "gemini-2.5-pro",
    "gen_ai.usage.input_tokens": 42105,
    "gen_ai.usage.output_tokens": 1024,
    "agent.name": "EnterpriseDevAgent",
    "agent.loop.iterations": 3
  }
}
```

## 4. 動態上下文預算與中斷機制 (Context & Preemption)

**預算分配公式**：

$$\text{Context Budget} = \text{Model Max Tokens} - \text{Target Output Tokens (Reserved)} - \text{Safety Buffer}$$

**語境剪枝策略**：

- **工作記憶 (Working Memory)**：保留最新 $N$ 輪的原始對話，超出限制則背景調用 LLM 生成「摘要（Summary）」。
- **聲明式記憶 (Declarative Memory)**：使用語義相似度過濾向量資料庫（Vector DB）內容，設定 $0.78$ 以上門檻避免無關雜訊。
- **超時與循環阻斷 (Preemption)**：當 LLM 出現「邏輯死循環」或對同一個 API 連續發出 5 次錯誤請求時，執行階段（Runtime）必須在指定步數（如 Max 10 Steps）內主動阻斷，並執行 Fallback 降級處理與優雅報錯。

## 5. 生產就緒檢核清單 (Production Checklist)

在將任何代理人系統推向生產環境前，請確認已落實以下檢核點：

- [ ] **受控副官防禦**：如果用戶輸入注入指令要求越權操作，後端工具層是否能依靠 JWT/ACL 強制拒絕，而非僅僅依賴 Prompt 拒絕？
- [ ] **模型無關性 (Model-agnostic)**：工具與 LLM 驅動層是否完全解耦？若明天更換底層大模型，是否能做到一條工具程式碼都不改？
- [ ] **超時熔斷**：系統是否能在指定步數（如 Max 10 Steps）內主動阻斷死循環？
- [ ] **Context 溢出保護**：當多輪對話長度接近臨界值時，系統是否能自動對歷史對話進行 Sliding Window 裁剪或自動摘要？
- [ ] **標準化軌跡 (Trace)**：當線上用戶報錯時，後端是否能一鍵調出該次決策的 OpenTelemetry 結構化 Trace 進行快速除錯？

## 6. 當前專案完成狀態

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
- VitePress 文檔網站已部署至 Cloudflare Pages
