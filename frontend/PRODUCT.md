# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Employees (role `user`)**: staff in business departments (ฝ่ายการเงิน, ฝ่ายขาย, ฝ่ายทรัพยากรบุคคล) who report a problem when a computer or piece of equipment stops working. They use it occasionally, under mild stress, and want to file a report and get back to work.
- **IT staff (`junior_technician`, `senior_technician`, `it_manager`)**: people in ฝ่ายไอที who work the queue on a desktop all day: triage, assign, update and close requests.

## Product Purpose

An internal IT repair-request system (ระบบแจ้งซ่อม) that lets employees report equipment problems and lets IT track them through to resolution. It is currently built as an interview take-home for ZenithComp; reviewers judge code quality and UI together.

## Operating Context

- Interface language is Thai; all copy is Thai.
- Accounts are provisioned by the organization; there is no self sign-up. Login is by email and password (Laravel Sanctum SPA session, throttled to 5 attempts per minute).
- Every user belongs to a department and holds one of four roles: `user`, `junior_technician`, `senior_technician`, `it_manager`.
- Inactive accounts are blocked by the API.

## Capabilities and Constraints

- Built so far: login, current-user (`/me`), logout. Repair-request features are not built yet.
- Stack: Vue 3 + Vite + TypeScript + Tailwind CSS v4 + Pinia; Laravel API backend.
- Role values are raw English keys in the API; Thai display labels are undecided.

## Brand Commitments

None. No logo, name treatment or corporate colors exist. Do not invent a company logo.

## Evidence on Hand

Seeded departments and demo accounts only (`backend/database/seeders`). No real ticket data, metrics or testimonials: do not fabricate any.

## Product Principles

1. Getting a problem reported fast beats explaining the system.
2. IT staff live in this tool all day: density, scanability and calm come before decoration.
3. Role and department decide what someone sees; make that visible.
4. Honest states: show connection, permission and error states plainly in Thai.
