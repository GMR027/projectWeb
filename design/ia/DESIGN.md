---
name: Vanguard CivilTech · IA
colors:
  surface: '#0b1326'
  surface-dim: '#0b1326'
  surface-bright: '#31394d'
  surface-container-lowest: '#060e20'
  surface-container-low: '#131b2e'
  surface-container: '#171f33'
  surface-container-high: '#222a3d'
  surface-container-highest: '#2d3449'
  on-surface: '#dae2fd'
  on-surface-variant: '#e0c0b1'
  inverse-surface: '#dae2fd'
  inverse-on-surface: '#283044'
  outline: '#a78b7d'
  outline-variant: '#584237'
  surface-tint: '#ffb690'
  primary: '#ffb690'
  on-primary: '#552100'
  primary-container: '#f97316'
  on-primary-container: '#582200'
  inverse-primary: '#9d4300'
  secondary: '#89ceff'
  on-secondary: '#00344d'
  secondary-container: '#00a2e6'
  on-secondary-container: '#00344e'
  tertiary: '#4edea3'
  on-tertiary: '#003824'
  tertiary-container: '#00b07a'
  on-tertiary-container: '#003b26'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#ffdbca'
  primary-fixed-dim: '#ffb690'
  on-primary-fixed: '#341100'
  on-primary-fixed-variant: '#783200'
  secondary-fixed: '#c9e6ff'
  secondary-fixed-dim: '#89ceff'
  on-secondary-fixed: '#001e2f'
  on-secondary-fixed-variant: '#004c6e'
  tertiary-fixed: '#6ffbbe'
  tertiary-fixed-dim: '#4edea3'
  on-tertiary-fixed: '#002113'
  on-tertiary-fixed-variant: '#005236'
  background: '#0b1326'
  on-background: '#dae2fd'
  surface-variant: '#2d3449'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.015em
  headline-md:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
    letterSpacing: -0.005em
  body-lg:
    fontFamily: Inter
    fontSize: 15px
    fontWeight: '400'
    lineHeight: 22px
  body-md:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
  body-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-mono-lg:
    fontFamily: JetBrains Mono
    fontSize: 13px
    fontWeight: '500'
    lineHeight: 18px
    letterSpacing: 0.02em
  label-mono-md:
    fontFamily: JetBrains Mono
    fontSize: 11px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.03em
  label-mono-sm:
    fontFamily: JetBrains Mono
    fontSize: 10px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.04em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 0.75rem
  gutter-mobile: 0.5rem
  margin: 1rem
  margin-mobile: 0.75rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 0.75rem
  space-lg: 1rem
  space-xl: 1.5rem
---

## Brand & Style

This design system targets structural engineers, civil project managers, and BIM coordinators working in mission-critical construction planning. The interface evokes precision, field-tested reliability, and surgical spatial clarity. The aesthetic merges **technical utility** with **modern high-density SaaS**: deep slate canvas surfaces evoke specialized CAD/GIS environments, contrasted by vibrant construction safety accents and telemetry-grade status cues.

Visual hallmarks:
- High-density toolbars, compact tabular metrics, and multi-pane spatial canvases.
- Restrained glassmorphic heads-up displays (HUDs) layered directly over 2D/3D civil project viewports.
- High-contrast structural boundaries with razor-sharp 1px dividers, keeping complex spatial dependencies legible under poor field-lighting conditions.

## Colors

The palette is engineered around an architectural dark room concept to prevent screen fatigue during prolonged Gantt, 4D CAD, and terrain inspection sessions.

- **Background Canvas (`#0F172A`)**: Base slate layer for viewport surround, backdrops, and top-level canvas.
- **Deep Well (`#0B1120`)**: Recessed canvas views, timeline tracks, and coordinate displays.
- **Elevated Surfaces (`#1E293B`)**: Inspector sidebars, modal overlays, tool dock containers, and active planning cards.
- **Structural Borders (`#334155`)**: Crisp 1px structural framing across panels, grid subdivisions, and inputs.
- **Primary Accent (`#F97316`)**: Construction safety orange reserved strictly for forward-action CTAs, milestone locks, critical path tasks, and cursor selection highlights.
- **Secondary Accent (`#0EA5E9`)**: Technical cyan for active coordinates, spatial snap points, measurement vectors, and link states.
- **Status Accents**:
  - Emerald (`#10B981`): Structural sign-offs, inspections passed, tolerance clearances.
  - Amber (`#F59E0B`): Schedule slippage, load warnings, material delay advisories.
  - Rose (`#EF4444`): Structural clash, safety stop-work orders, load-bearing violations.
- **Typography**: Primary readable text at `#F1F5F9` (Slate-100) with technical metadata and axis scales at `#94A3B8` (Slate-400).

## Typography

The type system prioritizes structural hierarchy and scanning efficiency.

- **Primary UI (Inter)**: Drives clear hierarchical distinction across sheet navigation, Gantt breakdown structures, and contextual action menus. Font weights are confined to 400 (regular), 500 (medium), and 600 (semibold) to avoid visual clutter in data-dense layouts.
- **Technical & Metric Scale (JetBrains Mono)**: Handles all spatial coordinates (Northing/Easting/Elevation), dimension readouts (linear meters, cubic volume, grade tolerances), timestamp records, and revision hashes. Monospaced tabular alignment guarantees vertical scanning stability during live parameter scrub-ins.
- All technical micro-labels (`label-mono-sm`) render in uppercase with positive letter spacing (`0.04em`) to ensure legibility when overlaid against 3D mesh viewports.

## Layout & Spacing

This design system uses a dense fixed-anchor layout system optimized for multi-pane engineering workstations:

- **Desktop (>=1280px)**: Three-tier split canvas. Fixed 64px global navigation rail, collapsible 320px work breakdown/phase tree, fluid central viewport (Gantt/BIM/Orthophoto canvas), and fixed 360px inspector/clash panel. Gutters maintain a tight `0.75rem` (12px) rhythm to maximize active viewport space.
- **Tablet / Field Laptops (768px - 1279px)**: Inspector collapses into a floating slide-over drawer; secondary tools transition into bottom dock trays.
- **Mobile Handheld (<768px)**: Canvas collapses to view-only mode; data sheets stack vertically with sticky execution headers; margins drop to `0.75rem` (12px).
- Internal element spacing is strictly constrained to a 4px/8px incremental base (`space-xs` = 4px, `space-sm` = 8px, `space-md` = 12px, `space-lg` = 16px).

## Elevation & Depth

Visual depth is achieved through **low-contrast outlines** complemented by **subtle glassmorphism** and dark tonal tiers, deliberately avoiding heavy drop shadows that muddy dense line graphics:

- **Level 0 (Recessed Well / CAD Viewport)**: Solid `#0B1120`. No borders or inner glow. Serves as infinite coordinate space.
- **Level 1 (Base Frame & Canvas Dividers)**: `#0F172A` outlined with crisp 1px borders in `#334155`.
- **Level 2 (Panels, Drawers, Inspectors)**: `#1E293B` surrounded by 1px `#334155` border.
- **Level 3 (HUD Overlays, Floating Toolpalettes)**: Translucent surface `rgba(30, 41, 59, 0.75)` with `backdrop-filter: blur(12px)` and a subtle highlight top-border `rgba(241, 245, 249, 0.1)`.
- **Level 4 (Modals & Clash Resolve Dialogs)**: Solid `#1E293B` elevated with an ultra-diffused shadow `0 20px 40px -15px rgba(0, 0, 0, 0.6)` and primary accent outline trace (`rgba(249, 115, 22, 0.2)`).

## Shapes

The geometric form language balances industrial rigidity with tactile precision:

- **Base Radius (`0.5rem` / 8px)**: Applied uniformly to interactive elements, metric chips, inputs, button groups, and toolbar toggles.
- **Panel & Modal Radius (`1rem` / 16px via `rounded-lg`)**: Applied to inspector cards, dialog sheets, and floating HUD overlays.
- **Pill Formats**: Reserved exclusively for live field status indicators, safety state badges, and spatial marker tags.

## Components

### Buttons & Action Controls
- **Primary**: Background `#F97316` (Construction Orange), text `#0B1120` (Bold), hover state `#EA580C`. High contrast ensures zero ambiguity on task commit actions.
- **Secondary / HUD Action**: Background `rgba(30, 41, 59, 0.8)` with `1px solid #334155`, text `#F1F5F9`. Hover activates an inner border in `#0EA5E9`.
- **Ghost Tool Item**: Compact square (32x32px), transparent background, icon color `#94A3B8`. Active tool toggle sets background to `#334155` with `#0EA5E9` left-edge indicator.

### Input Fields & Scrubbers
- Inputs use background `#0B1120`, border `1px solid #334155`, text `#F1F5F9`.
- Focus state replaces border with a sharp `1px solid #0EA5E9` and a zero-blur outline ring `2px solid rgba(14, 165, 233, 0.2)`.
- Number scrubbers feature fixed monospaced labels for units (`m³`, `kN`, `mm`, `deg`) anchored to the right edge in `#94A3B8`.

### Status Badges & Chips
- Compact height (20px), text set in `label-mono-sm`.
- Styled as semi-translucent fills (`rgba` at 15% opacity) coupled with `1px` border matching the status token:
  - Clash Detected: Border `#EF4444`, Fill `rgba(239, 68, 68, 0.15)`, Text `#FCA5A5`.
  - In Progress: Border `#0EA5E9`, Fill `rgba(14, 165, 233, 0.15)`, Text `#7DD3FC`.
  - Sign-off Complete: Border `#10B981`, Fill `rgba(16, 185, 129, 0.15)`, Text `#6EE7B7`.

### Cards & Work Breakdown Lists
- **Cards**: Surface `#1E293B`, framed in `1px solid #334155`. Critical-path items display an active left border stripe of `3px solid #F97316`.
- **Dense Data Lists**: Row heights fixed at 36px. Alternating hover background `rgba(51, 65, 85, 0.4)`. Dividers use hair-line borders (`#334155`). Column headers styled with uppercase monospaced labels (`label-mono-sm`) with integrated sorting arrows.

### Viewport HUD & Dimension Overlays
- Floating spatial HUD panels feature backdrop blur (`12px`), dark glass background (`rgba(15, 23, 42, 0.8)`), and border `1px solid rgba(51, 65, 85, 0.8)`.
- Measurement calipers and dynamic spatial tags use `#0EA5E9` stroke geometry with high-contrast JetBrains Mono callouts.

## AI Operations Console (ProjectWeb IA)

This extension applies Vanguard CivilTech to the AI section of ProjectWeb: the **Programador de actividades**, **Inconsistencias**, **Propuestas de IA** and **Revisiones** screens. The AI is an assistant inside a construction planning workstation, never an autonomous operator.

### Product principles

- **Human approval is the product.** Every screen states, in a compact trust strip, that the AI proposes and the user approves. No automatic application is implied anywhere.
- **Evidence before opinion.** Findings show the source (sistema/IA), the affected activities, a short evidence quote when available and the proposed action. The user can accept, reject or correct.
- **Calm density.** The console is information-dense but not noisy: one accent per intent, 1px borders, restrained glow and no decorative gradients behind data.
- **Traceability.** Dates, model names, run IDs, precision and history use the monospaced label scale so the user can compare runs at a glance.

### Three-zone console layout

- **Desktop (>= 1280px)**: `280px` context rail (scenarios, analysis runs, tools or review domains) + fluid canvas + `320px` inspector (approval, health, risk, legend). Page header spans the full width above the grid. Gutters `12px`; the rail and inspector are sticky within the viewport.
- **Tablet (768px - 1279px)**: the rail becomes a horizontal scrollable chip/selector strip above the canvas; the inspector moves below the canvas as a two-column summary or a collapsible panel.
- **Mobile (< 768px)**: single column. Header becomes compact; KPIs display two per row; tables become stacked cards; primary actions live in a sticky bottom bar. The inspector becomes a chain of collapsible sections (`<details>`) below the main content.
- The console must never introduce a second app sidebar: the application shell already provides global navigation.

### AI-specific components

#### Trust strip
Full-width compact band under the page header: shield icon, text "La IA propone; tú apruebas. Nada se aplica solo." and a trailing link to history or risk settings. Background `rgba(14, 165, 233, 0.08)`, 1px border `rgba(14, 165, 233, 0.28)`, radius `0.75rem`, height 40-48px. On mobile it wraps to two lines and the link moves below.

#### Context rail item
A compact navigable row: icon or severity dot, title, monospaced metadata (date, model, count), optional status badge. Height 44-52px, radius `0.6rem`, 1px transparent border. Active state uses `rgba(249, 115, 22, 0.12)` fill, `rgba(249, 115, 22, 0.38)` border and an orange 2px left indicator. Hover uses `rgba(255, 255, 255, 0.04)`.

#### Command bar
Sticky under the header when the canvas scrolls: search field (flex 1), filter chips or compact selects, view toggle (Lista / Por problema) and primary action. Height 44-52px; background `rgba(15, 23, 42, 0.86)` with `backdrop-filter: blur(12px)`; 1px bottom border. On mobile the search takes a full row and filters scroll horizontally.

#### Finding card
Surface `#1E293B`, 1px border `#334155`, radius `0.9rem`, 4px severity rail on the left:
- Crítica: `#EF4444`
- Alta: `#F97316`
- Media: `#F59E0B`
- Baja: `#0EA5E9`
Header: severity badge (mono, uppercase), title (`headline-sm`), meta line (category · source · model). Body: description, optional evidence quote with a thin cyan left rule, activity chips with monospaced codes and a lock chip for restrictions. Solution block: tinted surface `rgba(249, 115, 22, 0.06)`, action tag and proposed text. Footer: accept (primary), reject (ghost), correct (ghost) and a polite live-region message. Accepted/rejected cards desaturate to 65% and show the resolver and timestamp.

#### Risk badge
Compact pill, mono uppercase: "Simple" (neutral surface + muted text) or "Pide permiso" (orange-tinted fill + orange border + orange text). Never use red for risk level; red is reserved for critical findings and destructive actions.

#### Compliance score ring
A 96-112px conic-gradient ring driven by the reviewed percentage, with the percentage in JetBrains Mono at the center and a one-line label below. The ring uses `--success` when >= 80%, `--warning` when 50-79%, `--danger` below 50%. No animation beyond a 400ms reveal, disabled under `prefers-reduced-motion`.

#### Empty state
A single centered panel with a Lucide/Font Awesome outline icon in a 48px tinted square, a short title, one explanatory paragraph and one primary action. No illustrations or mascots.

#### Sticky approval footer
On mobile, the primary approval action and the count of pending changes remain reachable in a bottom bar with safe-area padding. On desktop the same actions sit in the inspector and never duplicate.

### Color semantics for AI

- **Cyan (`#0EA5E9`)**: AI insight, suggested relationships, evidence, monitoring, "what the AI detected". Never use cyan for destructive actions.
- **Orange (`#F97316`)**: user commitment (accept, apply, save, export) and critical path. Orange is the only primary CTA color.
- **Emerald (`#10B981`)**: accepted, complete, correct, up to date.
- **Amber (`#F59E0B`)**: needs review, stale tool, warning.
- **Rose (`#EF4444`)**: critical finding, overdue, destructive action. Do not use rose for generic emphasis.
- **Neutral (`#94A3B8` / `#CBD5E1`)**: metadata, secondary labels, disabled states.

### Content and tone

- UI copy is **Spanish (es-MX)**, concise and technical: "Aceptar y aplicar", "Rechazar", "Corregir aquí", "Pide permiso", "Análisis completo", "Sin cambios".
- Do not anthropomorphize the model. Say "la IA detectó", "la propuesta", "el análisis", not "la IA cree" or "el asistente opina".
- Always name the consequence before the action: "Se actualizarán 12 actividades" / "No modifica el programa".
- Dates use `dd/mm/yyyy`; technical identifiers, durations and deltas use monospace.
- Empty states explain how to generate the first result (desktop app, "Revisar ahora", etc.) without marketing language.

### Motion

- 150-200ms ease-out for hover, active and reveal; no infinite pulses or glowing loops.
- The trust strip, score ring and finding cards may fade/slide in once on load.
- Respect `prefers-reduced-motion: reduce`; all motion becomes instant.

### Accessibility

- Every icon-only action has an `aria-label`; every rail item is a real link with `aria-current="page"` when active.
- Focus-visible uses a 2px cyan outline with 2px offset over both light and dark surfaces.
- Severity is never conveyed by color alone: pair it with a text label and icon.
- Live messages use `role="status"`/`aria-live="polite"`.
- The three-zone grid reading order is header → rail → canvas → inspector; on mobile it becomes header → trust strip → KPIs → canvas → inspector.
- Minimum touch target 44px; contrast at least 4.5:1 for body text.

### Sample data for screen generation

Use realistic Spanish construction data:
- Project: "Torre Central · Etapa 2", code "TC-02".
- Programador: 184 activities, 12 change, finish 2 business days earlier, 7 obstacles, proposal from 24/11/2026 · 09:40, model "Qwen2.5 3B".
- Inconsistencias: 18 actionable, 6 observations, 4 overdue, precision 82%, run "Análisis semana 47".
- Propuestas: 23 pending, 9 low risk, 14 require permission; tools: Inconsistencias 11, Materiales 7, Programador 5.
- Revisiones: 78% reviewed, 42 materials, 186 activities, 9 RFI, 8 AI tools (5 up to date, 2 stale, 1 not run).

### Stitch generation brief

Generate the content area only, without the application's global sidebar, top bar or breadcrumb. Desktop frame 1440px wide; mobile frame 390x844. Use dark mode only for the source screens; the implementation maps every token to the application's light/dark variables. Prefer flat 1px-bordered surfaces over heavy shadows. Keep tables legible at 13px body and 11px mono metadata.
