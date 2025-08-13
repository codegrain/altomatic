# Changelog

All notable changes to this project will be documented in this file.

## 1.0.1 - 2025-08-13
### Fixed
- Settings page now renders inside Craft CP layout.
- Sidebar button event updated to `Element::EVENT_DEFINE_SIDEBAR_HTML` with `DefineHtmlEvent`.
- Per-asset button route fixed to use path param (`/altomatic/generate/asset/<id>`).
- Registered `altomaticService` component to avoid “Unknown component ID” errors.
- Method signature matched Craft 5: `getSettingsResponse(): mixed`.

## 1.0.0 - 2025-08-13
- Initial release.