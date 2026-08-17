# CLAUDE.md — LMS Capstone

Claude Code อ่านไฟล์นี้อัตโนมัติเมื่อเริ่มทำงานในรีโป. เนื้อหาหลักอยู่ใน `AGENTS.md`.

@AGENTS.md

## ทีม Engineering ที่ติดตั้งแล้ว (4 read-only + 2 เขียนได้)

รีโปนี้มี subagent ใน `.claude/agents/`:

**อ่านอย่างเดียว (read-only บังคับจริง):**
- **`lms-orchestrator`** *(+Task)* — คำสั่งเดียวจบ: วางแผน+กำกับลำดับเรียก subagent อื่น (locate→audit→fix→test→review) แล้วสรุปผล; ไม่แก้โค้ดเอง
- **`lms-locator`** — สำรวจโครงสร้าง หา `file:line` + reference pattern
- **`lms-reviewer`** — ตรวจโค้ด จบด้วย `APPROVED` / `CHANGES_REQUESTED`
- **`lms-security`** — ตรวจช่องโหว่ (SQL injection, JWT, authz/IDOR, secret) แบบ defensive
- **`lms-architecture`** — คำแนะนำ schema / index / query performance / layering

**เขียนได้:**
- **`lms-dev`** *(Edit/Write/Bash)* — แก้บั๊ก/เพิ่มฟีเจอร์ minimal diff แล้ว hand off ให้ tester→reviewer
- **`lms-tester`** *(Write/Bash — เฉพาะ test file)* — เขียน+รันเทสต์ รายงาน pass/fail; ไม่แก้ source

### วิธีสั่ง
- **Auto คำสั่งเดียว (แนะนำ):** "ใช้ lms-orchestrator ทำครบวงจรบน lms-backend/api/issues.php: audit → fix → test → review" — มันจัดลำดับให้เอง (dev/tester ยังต้อง approve การเขียน)
- อัตโนมัติรายตัว: พิมพ์งานปกติ Claude Code เลือก subagent จากคำอธิบายให้เอง เช่น "แก้บั๊กการคิดค่าปรับใน lms-backend/api/issues.php"
- เจาะจง: "ใช้ `lms-security` ตรวจ `lms-backend/api/auth.php`"
- ครบวงจร: "แก้บั๊ก X แบบครบวงจร: locator → dev → tester → reviewer จน APPROVED"

> **สิทธิ์:** 4 ตัวแรกแก้ไฟล์ไม่ได้จริง (โดยตั้งใจ). `lms-dev` แก้โค้ดได้. `lms-tester` เขียนได้เฉพาะ test file (เป็นวินัยระดับ prompt — tooling ไม่ได้ล็อกให้เขียนเฉพาะ test).
