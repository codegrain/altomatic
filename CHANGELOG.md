# Changelog

All notable changes to this project will be documented in this file.

## 1.0.3 - 2025-10-20
### Major Changes
- Simplified UX by removing per-asset sidebar UI
- Custom logging system for better error tracking and debugging
- Removed individual asset generation endpoints for cleaner architecture

### Fixed
- Fixed Asset ID column showing "—" in Recent Actions by properly passing the first selected asset ID to logAction
- Fixed settings form field names (added `settings[...]` prefix) for proper data persistence
- Fixed permission checking in bulk actions - now uses proper permission validation
- Fixed OpenAI provider image payload structure (`image_url` type instead of `input_image`)
- Enhanced error handling and logging across all AI providers (OpenAI, Google Vision, AWS Rekognition, Azure Vision)
- Added proper error logging when providers return empty results
- Fixed local file path error handling in AltomaticService

### Changed
- **Text consistency**: Changed "ALT" to "Alt" throughout entire codebase (UI, code comments, documentation)
- Removed Notes column from Recent Actions dashboard table for cleaner interface
- Enhanced all AI providers with LoggingTrait for consistent error reporting
- Improved OpenAI provider with better error logging and empty response detection
- Updated composer.json description from "ALT text" to "Alt text"
- Added model validation to Settings class with provider range validation
- Replaced Craft::error() calls with LoggingTrait methods throughout codebase

### Added
- New `LoggingTrait` with custom log file support (`altomatic-{date}.log`)
- Better error tracking with timestamps and categories
- Enhanced provider error logging with detailed context
- Model validation in Settings class
- Improved debug capabilities across all components

## 1.0.2 - 2025-08-13
### Added
- Top-level **Altomatic** CP section with **Dashboard** and **Settings** subnav.
- Dashboard showing totals for images with/without Alt, plus a recent actions log.
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