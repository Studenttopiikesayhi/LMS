# AGENTS.md — LMS Capstone (ระบบบริหารจัดการร้านเช่าหนังสือ)

> Portable agent operating manual สำหรับทีม **Audit (read-only)**. AI coding agent ใด ๆ
> (Claude Code, Copilot, Cursor, …) ต้องอ่านไฟล์นี้ก่อนทำงานในรีโปนี้ และปฏิบัติตามบทบาท
> workflow และขอบเขตด้านล่าง. บน**Claude Code** บทบาท read-only ถูกบังคับเชิงโครงสร้างผ่าน
> `.claude/agents/*.md` (`tools:` allowlist) — แก้ไฟล์ไม่ได้จริง.

## 1. ภาพรวมโปรเจกต์

ระบบบริหารจัดการร้านเช่าหนังสือ — เว็บแอปสถาปัตยกรรมแยกส่วน (decoupled): **PHP REST API** (backend)
+ **HTML/JS/Bootstrap 5.1.3** (frontend) คุยกันผ่าน Fetch API (JSON). ยืนยันตัวตนด้วย **JWT HS256**
(เขียน `hash_hmac` เอง), แฮชรหัสผ่านด้วย **bcrypt**, เชื่อมฐานข้อมูล **MySQL `lms_db`** ด้วย **PDO +
Prepared Statements**. สิ่งที่ห้ามผิดพลาด: การคิดเงิน (ค่าเช่า/ค่าปรับ), การตัด/คืนสต็อก, และสิทธิ์
เข้าถึง (member ต้องไม่ทำงานแทน admin หรือแตะข้อมูล issue ของผู้อื่น).

**โครงสร้างจริง (monorepo):** โค้ด **backend** อยู่ใต้ `lms-backend/` — ดังนั้น path ตัวอย่างทั้งหมดในไฟล์นี้และในไฟล์บทบาทให้เติม prefix `lms-backend/` เสมอ (เช่น `lms-backend/api/auth.php`, `lms-backend/config/jwt.php`, `lms-backend/database.sql`). **frontend** อยู่ใต้โฟลเดอร์ frontend ของโปรเจกต์ (เช่น `lms-frontend/` — **ยืนยัน path จริงด้วย Glob ก่อนเสมอ**). prefix ของบทบาท = `lms`. รีโป: `Studenttopiikesayhi/LMS`.

## 2. Stack & commands

- **Stack:** PHP (REST API) + HTML/CSS/JavaScript + Bootstrap 5.1.3 + MySQL/MariaDB (`lms_db`); dev บน macOS + XAMPP
- **รัน backend:** เปิด XAMPP (Apache + MySQL) → วางโปรเจกต์ใน `htdocs/`
- **Syntax check (ผู้ใช้รันเอง นอกทีม audit):** `php -l api/xxx.php`
- **DB:** import `database.sql` ผ่าน phpMyAdmin / `mysql -u root lms_db < database.sql`

> ทีม Audit เป็น **read-only** — ไม่รันคำสั่งเปลี่ยนสถานะใด ๆ (ไม่มี Bash). คำสั่งด้านบนสำหรับผู้ใช้/บทบาท dev ในอนาคตเท่านั้น.

## 3. Core principles (ทีม Audit)

1. **One agent per responsibility.** แต่ละบทบาทมีงานแคบและระดับสิทธิ์ตายตัว — locator หา, reviewer ตัดสิน, security หาช่องโหว่, architecture ให้คำแนะนำ DB/สถาปัตยกรรม.
2. **Read-only คือ read-only.** ทั้ง 4 บทบาทอ่านอย่างเดียว — ไม่ `Edit`/`Write`/รันคำสั่งเปลี่ยนสถานะ. บน Claude Code บังคับผ่าน `tools: Read, Grep, Glob`.
3. **Review loop ชัดเจน.** reviewer ตอบ **`APPROVED`** หรือ **`CHANGES_REQUESTED`** เท่านั้น. เจอ bug/blocker/major ⇒ `CHANGES_REQUESTED`. ห้าม "พอใช้ได้ไปก่อน".
4. **Knowledge gate ก่อนทำงาน.** ทุกบทบาทอ่านชุดเอกสารในข้อ 7 ก่อน แล้วระบุว่าอ่านอะไรบ้างในผลลัพธ์.
5. **ยืนยันด้วยไฟล์จริง.** ห้ามรายงาน `file:line` หรือช่องโหว่ที่ไม่ได้เปิดอ่านจริง — ห้ามเดา ห้ามแต่ง.

## 4. Agent roles (5 read-only + 2 เขียนได้)

| Role | ระดับ | เขียนได้? | หน้าที่ |
|------|-------|-----------|---------|
| `lms-orchestrator` | strong | ❌ read-only (planner) | รับงานใหญ่คำสั่งเดียว → วางแผน + กำกับลำดับเรียก subagent อื่น (locate→audit→fix→test→review) → สรุปผลรวม. มอบงานแก้ให้ dev ไม่แก้เอง. |
| `lms-locator` | fast | ❌ read-only | สำรวจโครงสร้าง รายงาน `file:line` + reference pattern. ไม่ implement. |
| `lms-reviewer` | strong | ❌ read-only | ตรวจ checklist: correctness, types, conventions, regression, security. จบด้วย `APPROVED`/`CHANGES_REQUESTED`. |
| `lms-security` | strong | ❌ read-only | Defensive security review: SQL injection, JWT, authz/IDOR, secret, OWASP. ให้ secure fix — ไม่เขียน exploit. |
| `lms-architecture` | strong | ❌ read-only | คำแนะนำ schema/index/relation/query performance + layering. Advisory only. |
| `lms-dev` | mid | ✅ code (Edit/Write/Bash) | แก้บั๊ก/เพิ่มฟีเจอร์ minimal diff ตาม style เดิม แล้ว hand off ให้ tester→reviewer เสมอ. |
| `lms-tester` | mid | ✅ **test files เท่านั้น** (Write/Bash) | เขียน+รันเทสต์สำหรับ logic ที่เปลี่ยน รายงาน pass/fail. **ห้ามแก้ source** — รายงานกลับ dev. |

> **สิทธิ์:** 5 ตัวแรก read-only บังคับเชิงโครงสร้างจริง. `lms-orchestrator` มี Task (เรียก subagent อื่น) แต่ไม่มี Edit/Write/Bash = แก้ไฟล์เองไม่ได้. `lms-dev` มี Edit/Write/Bash. `lms-tester` มี Write/Bash — ข้อจำกัด "แก้เฉพาะ test file" เป็น **prompt-level**.
> **หมายเหตุ orchestrator:** ถ้า Claude Code เวอร์ชันนั้นไม่รองรับ subagent เรียก subagent (nested Task) orchestrator จะสลับเป็นโหมด "แผน + คำสั่งพร้อมก๊อป" ให้ผู้ใช้สั่งต่อทีละขั้นแทน.

## 5. Workflow chains

| งาน | Chain |
|-----|-------|
| สำรวจว่าโค้ดอยู่ไหน | `lms-locator` |
| ตรวจโค้ดก่อนถือว่าเสร็จ | `lms-locator` → `lms-reviewer` |
| ตรวจความปลอดภัย (auth/JWT/SQL/admin) | `lms-locator` → `lms-security` → `lms-reviewer` |
| **แก้บั๊ก / เพิ่มฟีเจอร์ (frontend)** | `lms-locator` → `lms-dev` → `lms-reviewer` |
| **แก้บั๊ก / เพิ่มฟีเจอร์ (backend, มี logic)** | `lms-locator` → `lms-dev` → `lms-tester` → `lms-reviewer` |
| **งานแตะ DB / schema** | `lms-architecture` → `lms-dev` → `lms-tester` → `lms-reviewer` |
| Audit อย่างเดียว (ไม่แก้โค้ด) | `lms-locator` → (`lms-security` + `lms-architecture`) → `lms-reviewer` |
| **ครบวงจร (แนะนำ):** audit → fix → test → re-review | `lms-locator` → `lms-security`/`lms-architecture` → `lms-dev` → `lms-tester` → `lms-reviewer` → (loop จน `APPROVED`) |
| **คำสั่งเดียวจบ (Auto):** ให้ orchestrator จัดให้ | `lms-orchestrator` (จะเรียก locator→audit→dev→tester→reviewer ให้เอง; dev/tester ยังต้องผู้ใช้ approve การเขียน) |

> Claude Code เลือก subagent อัตโนมัติจากคำอธิบายได้ หรือสั่งตรงก็ได้ (เช่น "ใช้ lms-security ตรวจ lms-backend/api/auth.php").
> **ข้อจำกัดจริง:** Claude Code ไม่บังคับลำดับ chain ตายตัว — ต้อง orchestrate ด้วยคำสั่ง (ดู README ข้อ "วิธีสั่งงาน"). Loop แก้→รีวิว: reviewer `CHANGES_REQUESTED` → `lms-dev` แก้เฉพาะที่ลิสต์ → `lms-tester` รันซ้ำ (ถ้าแตะ logic) → รีวิวใหม่ จน `APPROVED`.

## 6. Review loop rules

- Verdict มีค่าเดียวเท่านั้น: `APPROVED` | `CHANGES_REQUESTED`.
- เจอ bug/blocker/major/ช่องโหว่ ⇒ `CHANGES_REQUESTED` เสมอ. ห้าม approve งานที่รู้ว่าพัง.
- เมื่อ `CHANGES_REQUESTED`: ผู้ใช้ (หรือบทบาท dev ในอนาคต) แก้เฉพาะที่ลิสต์ → ตรวจซ้ำ จน `APPROVED`.
- ถ้าติดจริง ให้บอกตรง ๆ — ห้ามเคลมว่าเสร็จ.

## 7. Knowledge gate (อ่านก่อนทำงาน)

ทุกบทบาทอ่านสิ่งเหล่านี้ก่อน แล้วระบุว่าอ่านอะไร:

1. ไฟล์นี้ (`AGENTS.md`) + `README` (ถ้ามี)
2. `database.sql` — schema 3 ตาราง (users, books, issues)
3. `config/config.php`, `config/jwt.php` — การเชื่อม DB, CORS, JWT guard, helper
4. โค้ด endpoint ที่เกี่ยวข้องใน `api/*.php` และ JS caller ใน `js/*.js`

## 8. Boundaries — ห้ามทำเด็ดขาด

- ❌ บทบาท read-only (locator/reviewer/security/architecture) แก้/สร้าง/ย้ายไฟล์ หรือรันคำสั่งเปลี่ยนสถานะ (ทำไม่ได้อยู่แล้วเพราะไม่มี Write/Edit/Bash).
- ❌ reviewer/security/architecture "แก้ให้เลย" — ต้องรายงาน `file:line` + วิธีแก้ ให้ `lms-dev` ไปแก้.
- ❌ **`lms-tester` แก้ source code** — เขียนได้เฉพาะไฟล์ test (เช่นใต้ `lms-backend/tests/`) เจอบั๊กใน source ให้รายงานกลับ `lms-dev`.
- ❌ **`lms-dev` แก้ schema/migration แบบออกแบบเอง** โดยไม่ผ่าน `lms-architecture` ก่อน; ห้ามแก้ข้าม `lms-backend/`+`lms-frontend/` แบบเผลอในครั้งเดียว.
- ❌ **`lms-dev`/`lms-tester` เคลม "เสร็จ/ผ่าน" โดยไม่ได้รันจริง** — ต้องแยก "run & passed" ออกจาก "written, not run".
- ❌ ลบ/ย้าย/แก้ข้อมูล หรือรันคำสั่ง DB/git ทำลายล้าง โดยไม่มีคำเตือน + Backup/Rollback plan.
- ❌ แต่ง API, method, column, index, version, error message หรือ `file:line` ที่ไม่ได้อ่านจริง.
- ❌ security เขียน exploit/PoC/payload — ให้เฉพาะ mitigation + secure code.
- ❌ approve ทั้งที่มี bug / ช่องโหว่ / logic ผิด.
- ❌ ใช้ float กับเงิน / ต่อ string เข้า SQL / ข้าม `require_admin` / JWT ไม่ใช้ `hash_equals` — ต้อง flag ทันทีถ้าเจอ.
- ❌ commit secrets หรือ hardcode credential.

## 9. Verification (definition of done — สำหรับงาน audit)

ก่อนถือว่า audit เสร็จ บทบาทที่ทำต้อง:

1. อ่านไฟล์จริงที่เกี่ยวข้องครบ (ยืนยันทุก `file:line`).
2. ระบุชุดเอกสาร/ไฟล์ที่อ่าน (knowledge gate).
3. ให้ผลลัพธ์ตาม output format ของบทบาทนั้น — reviewer ต้องจบด้วย verdict.

## 10. การบังคับ read-only บนแต่ละเครื่องมือ

| เครื่องมือ | อ่านอะไร | บังคับ read-only |
|-----------|----------|------------------|
| **Claude Code** | `AGENTS.md` + `.claude/agents/*.md` | ✅ **บังคับเชิงโครงสร้าง** ผ่าน `tools:` allowlist (Read, Grep, Glob) |
| Cursor / Copilot / อื่น ๆ | `AGENTS.md` | ⚠️ prompt-level — ยึดขอบเขตข้อ 8 |

> Portable ทุกเครื่องมือ: **หลักการ บทบาท workflow review loop ขอบเขต**.
> เฉพาะ Claude Code: **การบังคับ read-only เชิงโครงสร้าง** (แก้ไฟล์ไม่ได้จริง).

## 11. Output formats

ดูรายละเอียดในไฟล์บทบาทแต่ละตัว (`.claude/agents/lms-*.md`). สรุป:
- **locator** → Summary / Files to edit / Reference files / Dependencies / Watch out for
- **reviewer** → Verdict + Critical / Warning / Verified
- **security** → Findings (by risk: Impact/Likelihood/Fix) / Verified OK / Still to check
- **architecture** → Findings (+ trade-off) / Migration impact / Verified OK
