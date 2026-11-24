---
name: ระบบแจ้งซ่อม
description: Internal IT repair-request system where every ticket is a parcel on a five-stop route.
colors:
  ground: "#f3f3ef"
  paper: "#ffffff"
  ink-950: "#121833"
  ink-900: "#1d2547"
  ink-800: "#2a3360"
  ink-700: "#3a4475"
  ink-600: "#4f5884"
  ink-500: "#666d93"
  ink-300: "#b6bacf"
  ink-200: "#d8dae4"
  ink-100: "#ebecf1"
  ink-50: "#f7f7f9"
  vermilion-50: "#fcefeb"
  vermilion-600: "#c4381f"
  vermilion-700: "#a32c16"
  kraft-50: "#f7f0e4"
  kraft-500: "#b98a4b"
  kraft-700: "#74531f"
  scan-50: "#e9f3ed"
  scan-600: "#2f7d4f"
  scan-700: "#24623e"
typography:
  headline:
    fontFamily: "Anuphan, IBM Plex Sans Thai, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.333
  title:
    fontFamily: "Anuphan, IBM Plex Sans Thai, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 600
  body:
    fontFamily: "Anuphan, IBM Plex Sans Thai, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.43
  label:
    fontFamily: "Anuphan, IBM Plex Sans Thai, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.8125rem"
    fontWeight: 500
  meta:
    fontFamily: "Anuphan, IBM Plex Sans Thai, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 400
  code:
    fontFamily: "JetBrains Mono, ui-monospace, monospace"
    fontWeight: 500
    letterSpacing: "0.01em"
    fontFeature: "tnum"
rounded:
  badge: "3px"
  sm: "4px"
  full: "999px"
spacing:
  cell-y: "12px"
  cell-x: "16px"
  panel-y: "20px"
  panel-x: "24px"
  stack: "20px"
  gutter-sm: "16px"
  gutter-md: "24px"
  gutter-lg: "32px"
components:
  button:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink-900}"
    typography: "{typography.body}"
    rounded: "{rounded.sm}"
    padding: "6px 14px"
    height: "36px"
  button-hover:
    backgroundColor: "{colors.ink-50}"
  button-primary:
    backgroundColor: "{colors.vermilion-600}"
    textColor: "{colors.paper}"
    rounded: "{rounded.sm}"
    padding: "6px 14px"
    height: "36px"
  button-primary-hover:
    backgroundColor: "{colors.vermilion-700}"
  field:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink-950}"
    typography: "{typography.body}"
    rounded: "{rounded.sm}"
    padding: "8px 12px"
    height: "40px"
  field-disabled:
    backgroundColor: "{colors.ink-50}"
    textColor: "{colors.ink-500}"
  panel:
    backgroundColor: "{colors.paper}"
    rounded: "{rounded.sm}"
    padding: "20px 24px"
  top-bar:
    backgroundColor: "{colors.ink-900}"
    textColor: "{colors.ink-100}"
    height: "56px"
  role-badge:
    backgroundColor: "{colors.ink-800}"
    textColor: "{colors.paper}"
    typography: "{typography.meta}"
    rounded: "{rounded.badge}"
    padding: "4px 8px"
  priority-critical:
    backgroundColor: "{colors.vermilion-600}"
    textColor: "{colors.paper}"
    rounded: "{rounded.badge}"
    padding: "2px 6px"
  priority-high:
    textColor: "{colors.vermilion-700}"
    rounded: "{rounded.badge}"
    padding: "2px 6px"
  priority-normal:
    textColor: "{colors.ink-600}"
    rounded: "{rounded.badge}"
    padding: "2px 6px"
  banner-error:
    backgroundColor: "{colors.vermilion-50}"
    textColor: "{colors.vermilion-700}"
    rounded: "{rounded.sm}"
    padding: "10px 14px"
  banner-ok:
    backgroundColor: "{colors.scan-50}"
    textColor: "{colors.scan-700}"
    rounded: "{rounded.sm}"
    padding: "10px 14px"
  waiting-callout:
    backgroundColor: "{colors.kraft-50}"
    textColor: "{colors.kraft-700}"
    rounded: "{rounded.sm}"
    padding: "16px"
---

# Design System: ระบบแจ้งซ่อม

Scope: the signed-in app (queue, ticket detail, new ticket, manager reports), all rendered inside the app shell. The login page is a separate legacy surface (IBM Plex Sans Thai / IBM Plex Mono on a stone ground with a signal-orange accent, tokens `--font-sans`, `--font-mono`, `--color-signal-*`, and the `body` base style). It is not part of this system; do not use those tokens on signed-in screens and do not restyle login from this file.

## Overview

**Creative North Star: "The Parcel Tracking Board"**

A repair ticket is a parcel moving between hands. Its status is its latest scan, the queue is a tracking board, and the detail page is the waybill with its scan history. Every screen sits under a postmark-indigo bar on pale label stock, with white hairline tables and panels carrying the work. The look is a logistics office, not a helpdesk: no sidebar, no floating cards, no coloured status pills.

Density comes first because IT staff work the queue all day on desktop. The queue uses three type sizes and ranks by weight and tabular figures, not by size. The one signature device is the five-stop route strip (แจ้งเรื่อง → ช่างระดับต้น → ช่างอาวุโส → อนุมัติอุปกรณ์ → ปิดงาน). Its stops sit at identical x positions on every row, so the queue lines up into columns of state. It repeats at full width on the detail page above the scan timeline.

Colour is rationed. Indigo ink does the structure, vermilion marks what can be pressed or what is critical, kraft means "waiting on someone else", and scan green means closed. Everything else is ink on white.

**Key Characteristics:**
- Postmark-indigo top bar over a label-stock ground; white panels and tables ruled with 1px ink hairlines.
- Five-stop route strip as the shared state vocabulary: filled, hollow, ringed or struck stops.
- Anuphan for all UI text; JetBrains Mono only for ticket numbers, timestamps, counts and codes.
- 4px corners, 3px on small badges, full circles only for route stops and the scan timeline.
- Flat: no drop shadows. The only shadows are ring outlines on stops, the hovered-row hairline and the field focus glow.

## Colors

One cold indigo ink family carries the structure. Three narrowly scoped signal hues each have exactly one meaning.

### Primary
- **Postmark Indigo** (ink-900): the top bar background, primary text, filled route stops, the active scan dot, text selection and report bar fills. This is the system's voice.
- **Night Ink** (ink-950): page titles, panel titles, ticket numbers and record values. Used where text needs the most weight.

### Secondary
- **Stamp Vermilion** (vermilion-600): the primary button, the focus outline, the text caret, the active nav underline, the ring in the postmark mark, and the critical priority badge fill. **Deep Vermilion** (vermilion-700) is the hover state for those fills, plus error text, the high-priority badge text and required-field asterisks. **Vermilion Wash** (vermilion-50) is the error banner ground only.

### Tertiary
- **Kraft** (kraft-500): the route stop for a ticket waiting on the requester or the approver. **Kraft Ink** (kraft-700) colours waiting status labels and stale-report notices. **Kraft Wash** (kraft-50) is the ground of the "waiting for information" callout.
- **Scan Green** (scan-600): the closed stop on the route. **Scan Ink** (scan-700) colours resolved status labels, success notes and the "closed" panel title. **Scan Wash** (scan-50) is the success banner ground.

### Neutral
- **Label Stock** (ground): the app background under everything.
- **Paper** (paper): panels, tables, fields, buttons and the inside of hollow stops.
- **Ink 800 / 700**: role badge fill and its ring in the top bar; field label text (800).
- **Ink 600**: secondary text, hints, table header text and the button hover border.
- **Ink 500**: tertiary text (timestamps under ticket numbers, unassigned, cancelled), the field hover border and the void strike.
- **Ink 300**: control borders, idle stops, top-bar secondary text, and the hovered row's inset hairline.
- **Ink 200**: panel and table borders and the route and timeline rails.
- **Ink 100**: row dividers, record dividers, report bar tracks and the hovered row fill.
- **Ink 50**: table header band, button hover fill, nested subfield ground and disabled fields.

### Named Rules
**The Pressable-or-Critical Rule.** Vermilion appears only on something you can press or focus (the primary action, the focus ring, the active nav tab), on critical or high priority, or on an error. A status label or decoration is never vermilion.

**The One Meaning Rule.** Kraft only means "waiting on someone outside IT", and scan green only means "closed". Neither colour is used for emphasis.

## Typography

**UI Font:** Anuphan (falling back to IBM Plex Sans Thai, then system sans), weights 400/500/600/700
**Code Font:** JetBrains Mono (falling back to ui-monospace), weights 500/600, tabular figures, letter-spacing 0.01em

**Character:** Anuphan is a plain, loopless Thai sans that keeps dense Thai copy calm. JetBrains Mono is the label-printer voice: it appears only where a value is a machine identifier or a figure.

### Hierarchy
- **Headline** (600, 1.5rem): one per page, for the page title or the ticket title.
- **Title** (600, 1rem): panel headings.
- **Body** (400, 0.875rem): table cells, record text, field values and buttons (500, or 600 on primary).
- **Label** (500, 0.8125rem): field labels and hints (hints use weight 400 in ink-600).
- **Meta** (400, 0.75rem): table headers (500), location and asset lines, actor lines in the timeline, stop names on the large strip and the legend.
- **Code** (JetBrains Mono 500/600): ticket numbers (600, underlined in the queue), dates and times, counts, asset codes, file sizes and pagination numbers.

### Named Rules
**The Three Sizes Rule.** A queue row uses only body and meta sizes plus mono. Rank inside a row comes from weight (400/500/600) and ink depth, never from a bigger size.

**The Machine Voice Rule.** Mono is reserved for identifiers, timestamps and figures. Thai prose, names and labels are never set in mono.

## Layout

Pages sit in a centred column, max 80rem (7xl) wide, with a 16px gutter that grows to 24px at sm and 32px at lg. The new-ticket form narrows to 48rem (3xl). Below the 56px top bar the page starts 32px down (24px on detail and create pages).

The queue is one full-width table inside a 1px ink-200 frame. Cells are 12px by 16px, and the route cell is 16px tall. Columns collapse progressively: route under md, status and priority under sm, requester under lg, and assignee under xl. On narrow screens, status, priority and a compact strip fold into the title cell. A route legend sits under the table from md up, with pagination below it.

The detail page stacks a header (mono ticket number, title, status, priority and level, with actions on the right), the full-width large strip in a panel, and then a two-column grid from lg: content and action panels on the left, and the scan-history panel in a fixed 24rem right column that stays pinned at top 16px. Panels are spaced 20px apart. Manager reports use a 3:2 two-panel grid from lg.

**The Fixed Stops Rule.** The compact strip is a fixed-width grid of five 12px stops with 20px gaps. It never stretches to its cell, so stops line up vertically down the whole queue.

## Elevation & Depth

The system is flat. Depth comes from the ground/paper split and 1px hairlines, never from cast shadows. Box-shadow is used only as an outline device:

### Shadow Vocabulary
- **Active stop ring** (`0 0 0 3px #fff, 0 0 0 5px ink-900`): marks the current stop while it is being worked. The scan timeline uses a 2px/4px version on its latest event.
- **Queued stop halo** (`0 0 0 3px #fff, 0 0 0 4px ink-300`): marks the current stop while it waits for a technician to claim it.
- **Hovered row hairline** (`inset 0 0 0 1px ink-300`, with ink-100 fill): the one lifted thing in the queue.
- **Field focus glow** (`0 0 0 3px rgb(196 56 31 / 0.22)`, border to ink-900).

### Named Rules
**The One Lifted Thing Rule.** Only the hovered or focused row (or the focused control) is raised off the page, and it is raised with a hairline and a tint, not a shadow.

## Shapes

Corners are 4px on panels, tables, fields, buttons, banners, subfields and the waiting callout. Small badges (role and priority) are 3px. Full circles appear only on route stops, legend dots and the scan-timeline dots. Rules are 1px (borders and dividers) or 2px (the route and timeline rails, and stop borders). A cancelled stop is struck with a 2px ink-500 bar rotated -40°.

## Components

### Buttons
Quiet, label-like controls; the primary one is the only stamp of colour on a screen.
- **Shape:** gently squared (4px); min-height 36px; 6px by 14px padding; 6px gap for a leading 16px Lucide icon.
- **Default:** paper fill, ink-300 border, ink-900 text at 500 weight.
- **Primary:** vermilion fill and border, white 600 text. Use at most one or two per view.
- **Hover:** default moves to an ink-50 fill with an ink-600 border; primary deepens to vermilion-700. Transitions run 120ms ease-out.
- **Focus:** 2px vermilion outline, 2px offset (app-wide `:focus-visible`).
- **Disabled:** 50% opacity and a not-allowed cursor; inside a busy fieldset the cursor is `wait`. File pickers are default buttons on a `<label>` that pick up the focus outline from their hidden input.

### Chips (badges)
- **Priority:** critical is a solid vermilion fill with white text; high is a vermilion-600 inset ring with vermilion-700 text; normal is an ink-200 inset ring with ink-600 text. All three use 12px semibold text and 3px corners. Priority is the only chip; status is plain coloured text, never a pill.
- **Role badge (top bar):** ink-800 fill, ink-700 ring, white 12px medium text, Thai role label.

### Cards / Containers
- **Panel:** paper, 1px ink-200 border, 4px corners, 20px by 24px padding, 20px above. Titled with a Title-size heading.
- **Record:** stacked entries inside a panel, 16px vertical padding, separated by 1px ink-100 rules.
- **Subfield:** a repeated-item fieldset on an ink-50 ground (4px, 12px/16px padding) with a 13px semibold legend.
- **Banners:** error (vermilion wash) and success (scan wash), 4px, 10px by 14px, body size. The kraft-wash callout marks "waiting for information".

### Inputs / Fields
- **Style:** paper, 1px ink-300 border, 4px, min-height 40px, 8px by 12px padding, 14px ink-950 text. Labels sit 6px above in 13px medium ink-800. Date inputs use mono.
- **Hover:** border goes to ink-500.
- **Focus:** border goes to ink-900, plus the 3px vermilion glow at 22% alpha. The caret is vermilion.
- **Disabled:** ink-50 fill, ink-500 text.

### Navigation
- **Top bar:** ink-900, 56px. On the left is the postmark mark (a vermilion ring crossed by two cancellation lines) with ระบบแจ้งซ่อม. Next come the nav tabs: full-height, ink-300 text, white on hover, and the active tab is white 500 with a 3px vermilion underline. On the right are name and department (md up), the role badge and an icon-only logout button.
- **Back link:** ink-600 text with an ArrowLeft icon, ink-950 on hover, above the page title.
- **Pagination:** hint text "หน้า n จาก m" with mono numbers, plus two default buttons.

### Route Strip (signature)
Five stops on a 2px ink-200 rail. Each stop's state is drawn by its form:
- **done:** filled ink-900. The ticket has passed this stop.
- **active:** filled ink-900 with a double ring. The ticket is here and being worked.
- **queued:** hollow, with an ink-900 border and an ink-300 halo. The ticket is here and waiting to be claimed.
- **waiting:** filled kraft-500. The ticket is here, waiting on the requester or approver.
- **closed:** filled scan-600, always on the last stop.
- **void:** hollow and struck through. The ticket was cancelled at this stop.
- **idle:** hollow ink-300. The ticket has not reached this stop.

Compact (12px stops) in the queue; large (18px stops, full width, stop names below in 12px, bold at the current stop) on the detail page. The whole strip is one `role="img"` with a Thai summary label. On phones, names under done and idle stops are hidden. Stops passed through escalation or equipment approval are filled from the event history.

### Scan Timeline
The ticket's history renders as a vertical list on a 2px ink-200 rail, with a 12px hollow ink-900 dot per event. The latest event is filled and ringed. Each entry has a Title-weight event name, an actor · mono timestamp meta line, then the status change and notes in body text.

## Do's and Don'ts

### Do:
- **Do** use the route strip, at the same fixed stop positions, wherever a ticket's position matters, and pair it with the Thai status label.
- **Do** set ticket numbers, dates, counts and codes in JetBrains Mono with tabular figures.
- **Do** keep vermilion to the primary action, focus, active nav, critical/high priority and errors.
- **Do** build new surfaces from panels (paper, 1px ink-200, 4px) on the label-stock ground under the indigo bar.
- **Do** colour status as text only: ink-950 by default, kraft-700 for waiting, scan-700 for resolved, ink-500 for cancelled.

### Don't:
- **Don't** add a sidebar, floating white cards with drop shadows, or coloured status pills.
- **Don't** use cast or elevation shadows; box-shadow is only for stop rings, the hovered-row hairline and focus.
- **Don't** use kraft or scan green for emphasis or decoration; each carries exactly one state.
- **Don't** set Thai labels, names or prose in mono, or enlarge type to rank items inside a dense row.
- **Don't** carry the login page's IBM Plex, stone and signal-orange tokens into signed-in screens.
