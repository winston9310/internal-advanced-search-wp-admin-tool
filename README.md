# Internal Advanced Search (WordPress Admin Tool)

**Internal Advanced Search** is a lightweight WordPress plugin designed for **editors and SEO managers** to improve the way they search and filter content inside the WordPress admin dashboard.

Unlike the default WordPress search (which is limited and keyword-only), this plugin provides a dedicated admin page where editors can:

- Search posts and pages by **keywords** (optional).
- Filter results by:
  - Post type (post, page, or any)
  - Post status (published, draft, any)
  - Category
  - Tag
  - Author
  - Date range (from/to)
- Combine multiple filters (e.g. “All posts in *Health* category by *Jon Zeller*”).
- Define custom **search rules** (keyword → tag/category/post mapping) using ACF Pro (optional).
- Sort results by **relevance** (keyword matches in title and content) or by **date** when no keywords are used.
- Pin or boost specific posts/categories/tags for given queries.

⚠️ This plugin is **for internal/editorial use only** and does not affect the frontend search seen by site visitors.

---

## Features

- 📑 Dedicated **admin search page** (`Internal Search`) accessible via the WordPress dashboard.
- 🔎 Keyword-based search with weighted relevance (title > content).
- 🏷️ Advanced filters (categories, tags, authors, post type, status, date range).
- ⚡ Supports **custom search rules** via ACF Options Page (optional).
- 📌 Allows “boost” or “pin first” logic for selected content.
- 🗂️ Results table with direct links to **Edit** and **View**.

---

## Installation

1. Download or clone this repository:
   ```bash
   git clone https://github.com/yourusername/internal-advanced-search.git

2. Upload the plugin folder to your WordPress site under:

  **wp-content/plugins/internal-advanced-search**

3. Activate the plugin from Plugins → Installed Plugins in WordPress.


