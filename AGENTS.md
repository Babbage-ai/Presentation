# AGENTS.md

## Project
Small PHP/MySQL cloud signage platform for Raspberry Pi screens.

## Goal
Build a practical, deployable Phase 1 MVP with:
- admin login
- media upload
- playlists
- screen assignment
- JSON API
- Raspberry Pi local HTML/JS player

## Tech constraints
- PHP 8+
- MySQL / MariaDB
- HTML/CSS/JS
- No framework
- No Composer unless absolutely necessary
- No React
- No Node backend

## Preferences
- Keep architecture simple
- Prefer procedural PHP or very light modular structure
- Favor readability over abstraction
- Avoid over-engineering
- Full files, not pseudo-code
- Make it deployable on normal hosting or VPS

## Security rules
- Use password_hash / password_verify
- Validate uploads by MIME and extension
- Never allow executable uploads
- Escape output in admin UI
- Protect admin pages with session auth
- Validate screen token for every player API call

## Done means
- Full SQL schema
- Shared includes
- Admin pages
- API endpoints
- Player files
- README
- Raspberry Pi setup guide
- Setup/seed instructions

## Output rules
- Show architecture summary first
- Then file tree
- Then full file contents grouped by file path
- Keep comments practical
- Do not omit important code