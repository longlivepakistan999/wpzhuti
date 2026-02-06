# QWE AI Academy — WordPress Theme

> **Site**: www.qwe.edu.pl
> **Theme Slug**: `qwe-developer-flavor`
> **Text Domain**: `qwe-developer-flavor`
> **Purpose**: Free AI education platform — teaching people how to use AI tools (ChatGPT, AI art, AI coding, etc.)

## Project Overview

This is a custom WordPress theme for an online AI education website. All content is **free**, there is **no user registration**, and **no video content** — purely text/image-based tutorials.

### Key Decisions

- **Single content type**: Tutorials (custom post type `tutorial`) — no separate blog section
- **No payments**: Everything is free, no e-commerce or payment integration
- **No user system**: No registration, login, or user dashboard
- **No video**: Content is text/image-based with code blocks and step-by-step guides
- **SEO-first**: Built-in structured data (JSON-LD), Open Graph, meta descriptions, breadcrumbs, canonical URLs

## Directory Structure

```
wpzhuti/
├── style.css                          # Theme metadata + full CSS design system
├── functions.php                      # Theme setup, enqueue, widgets, helpers
├── header.php                         # Site header with nav + search
├── footer.php                         # Site footer with widgets + links
├── index.php                          # Main fallback template
├── front-page.php                     # Homepage (university-style: hero + trust bar + departments + featured + advantages + latest + CTA)
├── single-tutorial.php                # Single tutorial page (TOC + content)
├── single.php                         # Single blog post page
├── archive-tutorial.php               # Tutorial listing with category filters
├── taxonomy-tutorial_category.php     # Category archive page
├── page.php                           # Default page template
├── page-about.php                     # Template Name: About Page
├── page-contact.php                   # Template Name: Contact Page
├── page-privacy.php                   # Template Name: Privacy Policy Page
├── search.php                         # Search results
├── 404.php                            # 404 error page
├── CLAUDE.md                          # This file
│
├── inc/
│   ├── custom-post-types.php          # Tutorial CPT + tutorial_category taxonomy + meta boxes
│   ├── customizer.php                 # Theme Customizer settings (hero, CTA, footer, social)
│   ├── template-tags.php              # Template tags (meta, categories, navigation, related)
│   ├── theme-hooks.php                # Hooks/filters (cleanup, title, resource hints)
│   └── seo.php                        # SEO (JSON-LD, Open Graph, meta description, breadcrumbs)
│
├── template-parts/
│   ├── hero/
│   │   └── hero-front.php             # Homepage hero section with stats
│   ├── course/
│   │   └── course-card.php            # Tutorial card component (used in grids)
│   └── content/
│       ├── content-card.php           # Blog post card component
│       └── content-none.php           # No results message
│
├── assets/
│   ├── css/                           # Additional CSS (empty, main styles in style.css)
│   ├── js/
│   │   └── main.js                    # Mobile menu, TOC generator, smooth scroll, search
│   ├── images/
│   │   └── logo.svg                   # Site SVG logo
│   └── fonts/                         # Custom fonts (if needed)
│
└── languages/                         # Translation files (.po/.mo)
```

## Custom Post Types

### Tutorial (`tutorial`)
- **Slug**: `/tutorial/`
- **Archive**: `/tutorial/` (all tutorials)
- **Taxonomy**: `tutorial_category` (slug: `/tutorial-category/`)
- **Meta Fields**:
  - `_qwe_difficulty`: `beginner` | `intermediate` | `advanced`
  - `_qwe_featured`: `1` | `0` (show on homepage)
- **Supports**: title, editor, thumbnail, excerpt, custom-fields, revisions

## Theme Customizer Settings

All customizable via **Appearance → Customize**:

| Setting Key | Section | Description |
|------------|---------|-------------|
| `qwe_hero_title` | Homepage Hero | Hero section main title |
| `qwe_hero_subtitle` | Homepage Hero | Hero section subtitle text |
| `qwe_hero_stats_tutorials` | Homepage Hero | Tutorial count stat (e.g., "200+") |
| `qwe_hero_stats_categories` | Homepage Hero | Category count stat |
| `qwe_hero_stats_readers` | Homepage Hero | Monthly readers stat |
| `qwe_cta_title` | Homepage CTA | CTA banner title |
| `qwe_cta_text` | Homepage CTA | CTA banner description |
| `qwe_footer_about` | Footer | Footer about text |
| `qwe_contact_email` | Footer | Contact email address |
| `qwe_social_twitter` | Social Links | Twitter/X URL |
| `qwe_social_github` | Social Links | GitHub URL |
| `qwe_social_youtube` | Social Links | YouTube URL |
| `qwe_social_facebook` | Social Links | Facebook URL |

## CSS Architecture

### Design Tokens (CSS Custom Properties)

All design values are defined as CSS variables in `:root` in `style.css`:

- **Colors**: `--color-primary` (#4F46E5), `--color-secondary` (#06B6D4), `--color-accent` (#F59E0B)
- **Typography**: `--font-sans` (Inter), `--font-heading` (Plus Jakarta Sans), `--font-mono` (JetBrains Mono)
- **Spacing**: `--space-1` through `--space-24` (0.25rem to 6rem)
- **Radius**: `--radius-sm` through `--radius-full`
- **Shadows**: `--shadow-sm` through `--shadow-xl`

### BEM Naming Convention

CSS classes follow BEM (Block Element Modifier):
```
.course-card              → Block
.course-card__title       → Element
.course-card__badge       → Element
.difficulty--beginner     → Modifier
```

### Responsive Breakpoints

- Desktop: > 960px
- Tablet: 768px – 960px
- Mobile: < 768px
- Small mobile: < 480px

## JavaScript

`assets/js/main.js` — Vanilla JavaScript (no jQuery), includes:

1. **Mobile menu toggle** — Hamburger menu for mobile
2. **Table of Contents** — Auto-generated from h2/h3 headings in tutorial content
3. **Sticky TOC** — Fixed sidebar TOC on scroll (desktop)
4. **Smooth scroll** — For anchor links
5. **Header search** — Expand/collapse animation

Data is passed from PHP via `wp_localize_script` as `qweData` object:
- `qweData.ajaxUrl` — WordPress AJAX URL
- `qweData.nonce` — Security nonce
- `qweData.siteUrl` — Home URL

## SEO Features

- **JSON-LD Structured Data**: WebSite, EducationalOrganization, Article, CollectionPage schemas
- **Open Graph tags**: og:title, og:description, og:image, og:type, og:url
- **Twitter Card**: summary_large_image
- **Meta description**: Auto-generated from page context
- **Canonical URLs**: On all singular and front pages
- **Breadcrumbs**: Schema.org BreadcrumbList markup
- **Semantic HTML**: article, nav, header, footer, main, time elements
- **Clean head**: Removes unnecessary WP meta tags (generator, RSD, wlwmanifest)

## Development Conventions

### PHP
- All functions prefixed with `qwe_`
- All meta keys prefixed with `_qwe_`
- Use `esc_html()`, `esc_attr()`, `esc_url()` for output escaping
- Use `wp_nonce_field()` / `wp_verify_nonce()` for form security
- Use `sanitize_text_field()`, `sanitize_email()`, `esc_url_raw()` for input sanitization
- Template parts loaded via `get_template_part()`

### CSS
- BEM naming convention
- CSS custom properties for theming
- Mobile-first responsive design
- No preprocessor — plain CSS

### JavaScript
- Vanilla JS, no dependencies
- IIFE pattern to avoid global scope pollution
- Event delegation where appropriate

### WordPress
- Text domain: `qwe-developer-flavor`
- All strings wrapped in `__()`, `_e()`, `esc_html__()`, `esc_html_e()`, `_n()`
- Theme supports: title-tag, post-thumbnails, html5, custom-logo, wp-block-styles
- Two nav menus: `primary`, `footer`
- Two widget areas: `sidebar-1`, `footer-1`
- Two image sizes: `qwe-tutorial-card` (640x360), `qwe-tutorial-hero` (1200x500)

## Adding New Content

### New Tutorial
1. Go to **Tutorials → Add New** in WP Admin
2. Set title, content (using block editor), excerpt, and featured image
3. Assign a **Tutorial Category**
4. Set **Difficulty Level** (Beginner/Intermediate/Advanced) in sidebar
5. Check **Featured** to show on homepage

### New Tutorial Category
1. Go to **Tutorials → Categories**
2. Add name, slug, and description
3. Optionally set icon via `_qwe_category_icon` term meta

### New Page (About, Contact, Privacy)
1. Create a new page in WP Admin
2. Under **Page Attributes**, select the appropriate template:
   - "About Page" for the about page
   - "Contact Page" for the contact page
   - "Privacy Policy Page" for privacy policy
