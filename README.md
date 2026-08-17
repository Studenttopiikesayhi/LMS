# ทีม Engineering สำหรับ LMS Capstone — วิธีติดตั้งใน Claude Code

ชุดนี้ทำให้ Claude Code มี **subagent 6 ตัว**: **4 ตัว audit ที่แก้ไฟล์ไม่ได้จริง** (locator, reviewer, security, architecture) + **2 ตัวเขียนได้** (dev แก้โค้ด, tester เขียน/รันเทสต์) — ครบวงจร **audit → fix → test → re-review** สำหรับโปรเจกต์ LMS (PHP REST API + JS).

## ไฟล์ในชุดนี้

```
โปรเจกต์ LMS ของคุณ/  (lms_project/)
├── AGENTS.md                      # คู่มือทีม (portable ทุกเครื่องมือ)
├── CLAUDE.md                      # ให้ Claude Code อ่าน AGENTS.md อัตโนมัติ (@AGENTS.md)
└── .claude/
    └── agents/
        ├── lms-orchestrator.md    # คำสั่งเดียวจบ: กำกับลำดับ subagent อื่น    (read-only +Task)
        ├── lms-locator.md         # สำรวจโครงสร้าง หา file:line          (read-only)
        ├── lms-reviewer.md        # ตรวจโค้ด → APPROVED/CHANGES_REQUESTED (read-only)
        ├── lms-security.md        # ตรวจช่องโหว่ (defensive)             (read-only)
        ├── lms-architecture.md    # คำแนะนำ DB/สถาปัตยกรรม               (read-only)
        ├── lms-dev.md             # แก้บั๊ก/เพิ่มฟีเจอร์                  (เขียนได้: Edit/Write/Bash)
        └── lms-tester.md          # เขียน+รันเทสต์                       (เขียนได้: Write/Bash — เฉพาะ test)
```

**สิทธิ์:** 5 ตัว audit/planner read-only. `lms-orchestrator` มี Task (สั่ง subagent อื่น) แต่แก้ไฟล์เองไม่ได้. `lms-dev` มี Edit/Write/Bash. `lms-tester` มี Write/Bash (ข้อจำกัด "แก้เฉพาะ test file" เป็นวินัยระดับ prompt).

**คำสั่ง Auto ตัวเดียวจบ (ผ่าน orchestrator):**
```
ใช้ lms-orchestrator ทำครบวงจรบน lms-backend/api/issues.php: audit (security+architecture) → dev แก้ → tester เขียน+รันเทสต์ → reviewer สรุป verdict — dev/tester ให้ฉัน approve การเขียนเอง
```

## ติดตั้ง (3 ขั้น)

1. **คัดลอกไฟล์ทั้งหมดลง root ของรีโป LMS** (ให้ `.claude/` อยู่ระดับเดียวกับโค้ด `api/`, `js/`).
   - ถ้ามี `CLAUDE.md` เดิมอยู่แล้ว: อย่าทับ — แค่เพิ่มบรรทัด `@AGENTS.md` ลงไป แล้ววาง `AGENTS.md` + `.claude/agents/` เพิ่ม.
2. เปิดโปรเจกต์ด้วย **Claude Code** ที่ root นั้น.
3. ตรวจว่าติดตั้งแล้ว: พิมพ์คำสั่ง

   ```
   /agents
   ```

   ต้องเห็น `lms-locator`, `lms-reviewer`, `lms-security`, `lms-architecture` ในรายการ.

## วิธีสั่งงาน

**อัตโนมัติ** (Claude Code เลือก subagent จากคำอธิบายให้เอง):
```
ตรวจความปลอดภัยของ api/auth.php ให้หน่อย
review โค้ดที่เพิ่งแก้ใน api/issues.php
หา endpoint ที่คิดค่าปรับอยู่ไฟล์ไหน บรรทัดไหน
```

**เจาะจง subagent:**
```
ใช้ lms-security ตรวจ api/issues.php เรื่อง IDOR กับ SQL injection
ให้ lms-architecture ดู index ของตาราง issues
ให้ lms-reviewer ตัดสิน diff ล่าสุด
```

**Audit อย่างเดียว (ไม่แก้โค้ด):**
```
รัน audit บน lms-backend/api/issues.php: locator → security + architecture → reviewer
```

**แก้บั๊ก/เพิ่มฟีเจอร์ (ให้ dev แก้จริง):**
```
แก้บั๊กการคิดค่าปรับใน lms-backend/api/issues.php ให้ค่าปรับ = วันเกิน × 10 (ไม่เกินกำหนด = 0)
```

**ครบวงจร audit → fix → test → re-review (แนะนำ):**
```
แก้บั๊ก X แบบครบวงจร:
1) lms-locator หา target file:line
2) lms-security + lms-architecture ตรวจถ้าเกี่ยวกับ auth/DB
3) lms-dev แก้แบบ minimal diff
4) lms-tester เขียน+รันเทสต์ business logic
5) lms-reviewer สรุป verdict — วนแก้จน APPROVED
```

## ขอบเขต / ข้อจำกัดที่ต้องรู้ (ตรงไปตรงมา)

| ประเด็น | สถานะจริง |
|--------|-----------|
| บังคับ read-only (แก้ไฟล์ไม่ได้) | ✅ จริง — ผ่าน `tools:` allowlist ใน frontmatter |
| Claude Code อ่าน `AGENTS.md` | ✅ ผ่าน `CLAUDE.md` ที่ `@AGENTS.md` (native อ่าน `CLAUDE.md`) |
| บังคับลำดับ chain อัตโนมัติ | ⚠️ **ไม่** — Claude Code เลือก/สั่งได้ แต่ไม่ล็อกลำดับ ต้อง orchestrate ด้วยคำสั่ง |
| `APPROVED/CHANGES_REQUESTED` | ⚠️ convention ระดับ prompt (subagent ทำตาม ไม่ใช่ engine บังคับ) |
| ตัว subagent แก้โค้ดให้ | ❌ โดยตั้งใจ — ให้ main agent หรือบทบาท dev เป็นผู้แก้ตามรายงาน |

## หมายเหตุความปลอดภัยของข้อมูล
- subagent เหล่านี้ **อ่านอย่างเดียว** จึงไม่มีความเสี่ยงลบ/เขียนทับโค้ด.
- `lms-security` เป็น **defensive-only** — หา mitigation/secure fix ไม่ผลิต exploit.
- ไม่มีการฝัง credential ใด ๆ ในไฟล์ชุดนี้.
- เอกสารอ้างอิงกลไก Claude Code (`.claude/agents/`, frontmatter `tools:`) อาจเปลี่ยนตามเวอร์ชัน — ควรเช็ก docs ทางการของ Claude Code อีกครั้งถ้าพฤติกรรมต่างจากนี้.
