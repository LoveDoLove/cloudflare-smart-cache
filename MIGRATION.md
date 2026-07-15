AI 長期記憶與代理人系統遷移指南 (Migration Guide for AI Long-Term Memory & Agentic System)

本文件引導你將現有的專案無痛遷移至全新的「神諭級 AI 代理人憲章與長期記憶架構」。請依照以下五個步驟執行，以確保原有的專案脈絡（Context）不流失，同時完美激活全新的安全、工具解耦與觀測性模組。

遷移五部曲 (The 5-Step Migration Workflow)

[步驟 1：備份現有檔案] ──> [步驟 2：建立全新憲章] ──> [步驟 3：合併舊專案脈絡]
                                                             │
                                                             v
[步驟 5：首次對話激活] <── [步驟 4：初始化記憶與技能目錄] ◄───┘


步驟 1：安全備份（Backup Existing Files）

在進行任何覆寫前，請先在你的終端機（Terminal）或編輯器中，將現有的 AGENTS.md 和 MEMORY.md 重新命名備份，避免歷史約定流失：

# 在專案根目錄下執行備份命令
mv AGENTS.md AGENTS.md.bak
mv MEMORY.md MEMORY.md.bak


步驟 2：建立全新憲章（Deploy New Frameworks）

在專案根目錄下，建立全新格式的檔案，並直接將我們最新的憲章範本寫入：

建立 AGENTS.md：將最新版本的「通用代理人工程架構與行為規範」內容完整複製並寫入此檔案。

建立 MEMORY.md：將最新版本的「AI 長期記憶與專案約定」內容完整複製並寫入此檔案。

步驟 3：合併舊專案脈絡（Merge Context & Preferences）

打開你剛才備份的 MEMORY.md.bak，並與新建立的 MEMORY.md 進行對照，將舊專案的資訊合併填入新版的 第 1 節 與 第 2 節 中：

3.1 更新專案基礎設定（MEMORY.md ➔ 第 1 節）

請將以下 xx 欄位替換為你舊備份檔中的真實數值：

應用名稱 (App Name): xx (例如：MySaaSApp)

資料庫 (App Database): xx (例如：PostgreSQL)

前端技術棧 (Frontend): xx (例如：React + Tailwind)

後端技術棧 (Backend): xx (例如：Node.js + Express)

主權部署環境 (Deployment): xx (例如：Vercel)

3.2 遷移用戶偏好（MEMORY.md ➔ 第 2 節）

如果你在 MEMORY.md.bak 中有定義特殊的代碼偏好、縮排規定（例如：2 Spaces 縮排）、特定的 API 呼叫限制等，請將它們完整複製並貼在新版 MEMORY.md 的「使用者偏好與互動慣例」下方。

步驟 4：初始化記憶與技能目錄（Initialize Directory Structure）

全新的架構依賴結構化的目錄來存放日誌與技能包。請在終端機中執行以下指令來自動生成所需的結構：

# 1. 建立記憶體資料夾
mkdir -p memory

# 2. 建立空白的任務追蹤看板
touch memory/tasks.md

# 3. 建立技能包存放目錄
mkdir -p .agents/skills

# 4. (選用) 如果你已有 karpathy-guidelines 技能包，請將其移動至此：
# mv path/to/old/karpathy-guidelines .agents/skills/karpathy-guidelines


4.1 初始化任務看板（memory/tasks.md）

打開剛建立的 memory/tasks.md，並將你目前正在進行、或是未來的開發計畫寫入。可以使用以下標準看板格式：

# AI 任務追蹤看板 (AI Task Board)

## [ ] Backlog (待辦事項)
- [ ] 實作使用者註冊驗證流程 (JWT)
- [ ] 整合外部 API 串接

## [>] In Progress (進行中)
- [>] 遷移 AI 代理人長期記憶架構

## [x] Completed (已完成)
- [x] 初始化專案 GitHub 倉庫


步驟 5：首次對話激活（Verify & Initialize Agent）

完成以上所有檔案配置與目錄初始化後，恭喜你！遷移工作已順利完成。

現在，請開啟一個全新的 AI 對話視窗，並輸入以下指令以完成激活：

「請讀取 AGENTS.md 和 MEMORY.md，恢復你的身份與工作狀態。接著，請檢查本地 .agents/skills/ 目錄與 memory/tasks.md 任務狀態，並告訴我我們接下來要進行什麼任務。」

AI 就會自動讀取這兩個檔案，完美恢復其具備頂端架構思維的「神諭級全端工程師」狀態，並與你當前的專案看板進度無縫對齊！