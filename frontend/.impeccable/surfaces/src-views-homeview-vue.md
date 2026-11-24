---
version: 1
slug: "src-views-homeview-vue"
primary_target: "src/views/HomeView.vue"
related_targets: ["src/views/TicketDetailView.vue","src/views/TicketCreateView.vue","src/views/ManagerReportsView.vue"]
---

# Signed-in app (queue, ticket detail, new ticket, manager reports)

Mode: Operate. Primary user: IT staff working the queue all day on desktop; employees file and follow their own tickets. Login page is out of scope and keeps its current look.

## Direction contract

THESIS: A repair ticket is a parcel moving between hands; its status is the latest scan and the queue is a tracking board. Refuses the sidebar + white cards + blue pill helpdesk.

OWN-WORLD: Postmark-indigo top bar and ink; label-stock ground with white hairline tables; vermilion only for what can be pressed or what is critical; kraft for "waiting on someone else"; scan green for closed. Anuphan for UI, JetBrains Mono only for ticket numbers, timestamps and figures. 4px corners, 1px rules, no decorative shadow. State is never colour alone: stops are filled, hollow, ringed or struck.

STORY: A technician sees at a glance where every ticket sits on its route and what is urgent, opens one, reads its scan history, and acts. An employee reports a problem in one step and can see whose hands it is in.

FIRST VIEWPORT: Indigo bar: postmark mark + ระบบแจ้งซ่อม, nav (ใบแจ้งซ่อม, รายงาน for managers), name, department, Thai role badge, logout. Page title with total count, primary action top right (แจ้งซ่อม for employees). Dense table: mono ticket no. | title + location/asset | five-stop tracking strip | status label | priority | requester/department | assignee. Pagination under it.

FORM: Parcel Tracking, position 5 of 7 on the ordered list, seed:51e6808f0a7a099c. Signature move: the five-stop route strip (แจ้ง → ช่างต้น → อาวุโส → อนุมัติ → ปิด) at identical x positions on every row, repeated large on the detail page above the scan timeline.

Raises: emission-rail (state by form, not hue); botanical folio (fixed stop positions so the queue aligns into columns of state); warm app (vermilion reserved for action); timetable (three type sizes in the queue, rank by weight and tabular figures); streaming wall (one lifted thing per screen: the hovered/focused row).

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
