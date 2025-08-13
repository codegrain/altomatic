# Changelog

All notable changes to this project will be documented in this file.

## 1.0.2 - 2025-08-13
### Added
- Top-level **Altomatic** CP section with **Dashboard** and **Settings** subnav.
- Dashboard showing totals for images with/without ALT, plus a recent actions log.
- Lightweight action logging (queue single, selected, all) with user and timestamp.
- Config guardrails: if provider creds/envs are missing, the sidebar shows a warning and controllers surface clear errors.
- Improved per-asset sidebar UI: labeled panel, primary action button, quick links.

### Changed
- Always writes to Craft’s native Asset **Alternative Text** (`$asset->alt`).
- Better “Generate for All” feedback and error surface in the CP toolbar button.

## 1.0.1 - 2025-08-13
### Fixed
- Settings page now renders inside Craft CP layout.
- Sidebar button event updated to `Element::EVENT_DEFINE_SIDEBAR_HTML` with `DefineHtmlEvent`.
- Per-asset button route fixed to use path param (`/altomatic/generate/asset/<id>`).
- Registered `altomaticService` component to avoid “Unknown component ID” errors.
- Method signature matched Craft 5: `getSettingsResponse(): mixed`.

## 1.0.0 - 2025-08-13
- Initial release.