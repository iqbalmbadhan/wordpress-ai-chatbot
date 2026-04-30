=== SiteChat AI ===
Contributors:      iqbalmahmud
Tags:              chatbot, ai, gemini, openai, customer support, knowledge base, faq
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      8.0
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

AI-powered chatbot trained on your WordPress content. Answers visitor questions with accurate information and source links using Google Gemini AI (free tier).

== Description ==

**SiteChat AI** turns your WordPress content into an intelligent chatbot that answers visitor questions — accurately, instantly, and with source citations.

= How it Works =

1. **Index** — SiteChat AI scans your posts, pages, WooCommerce products, and custom post types, chunks the content, and stores vector embeddings using Google's `text-embedding-004` model (free tier).
2. **Search** — When a visitor asks a question, the plugin embeds the query and performs cosine similarity search to find the most relevant content chunks.
3. **Answer** — The top chunks are fed as context to `gemini-2.0-flash`, which generates a conversational answer with links back to your pages.

= Features =

**4 Display Modes:**
* 🫧 **Floating Bubble** — Corner chat icon like Intercom or Crisp
* ➡️ **Slide-in Panel** — Slides from the right or left edge
* 📄 **Embedded Block** — Drop anywhere via Gutenberg block or `[sitechat]` shortcode
* 🖥️ **Full Page** — Dedicated `/chat` URL with immersive UI

**Content Indexing:**
* Indexes posts, pages, custom post types, WooCommerce products
* Smart paragraph-aware chunking with configurable size and overlap
* Auto re-indexes on post save
* Daily background re-indexing via WP-Cron
* Per-document status tracking (indexed / pending / failed)

**AI Powered by Google Gemini (Free Tier):**
* `text-embedding-004` — 768-dimension embeddings (1,500 requests/day free)
* `gemini-2.0-flash` — Fast, accurate answer generation (15 RPM free)
* No paid API plan required to get started

**Admin Dashboard:**
* Real-time indexing stats
* Per-document management (re-index, remove)
* Chat analytics with feedback tracking
* Full appearance customization
* Display rule controls (all pages / specific pages / exclude pages)

**Widget Customization:**
* Primary and secondary color pickers with live preview
* Custom bot name and avatar
* Configurable welcome message and placeholder
* Show/hide source links and "Powered by" footer
* Custom CSS override

**Developer Friendly:**
* Zero npm / build step — pure PHP + Vanilla JS
* WordPress Coding Standards compliant
* Full REST API endpoint (`/wp-json/sitechat/v1/chat`)
* Gutenberg block with `null` save (server-side render)
* `[sitechat]` shortcode with height/width attributes
* Translation ready (`.pot` included)

= Requirements =

* WordPress 6.0+
* PHP 8.0+
* A free [Google AI Studio](https://aistudio.google.com/apikey) API key

== Installation ==

1. Upload the `sitechat-ai` folder to `/wp-content/plugins/`
2. Activate through the **Plugins** menu
3. Go to **SiteChat AI → Settings** and enter your Gemini API key
4. Click **Test Connection** to verify the key works
5. Go to **SiteChat AI → Content** and click **Re-index All**
6. Go to **SiteChat AI → Appearance** and choose your display mode
7. Enable the chatbot in **Settings → Enable Chatbot**

== Frequently Asked Questions ==

= Is Google Gemini really free? =

Yes. Both `text-embedding-004` and `gemini-2.0-flash` have generous free tiers. You need a Google account to get an API key from [Google AI Studio](https://aistudio.google.com/apikey) — no credit card required.

= How many pages can I index? =

There's no hard plugin limit. Practical limits depend on the Gemini embedding API's free-tier rate (1,500 requests/day). Large sites can be indexed over multiple days using the daily cron job.

= Does it work with WooCommerce? =

Yes. Select the `product` post type in Settings → Indexing to index product descriptions.

= Can I embed the chat on a specific page only? =

Yes. In Appearance → Display Rules, select "Specific Pages Only" and choose the pages. Or use the `[sitechat]` shortcode / Gutenberg block for manual placement.

= Is visitor data stored? =

Only the question text, bot response, and a hashed (SHA-256) IP address are stored in the local database — never raw PII. Chat logs are visible in the Analytics tab.

= How do I add the chat to a page with a shortcode? =

`[sitechat]` — basic embed at default height
`[sitechat height="800"]` — custom height in pixels

== Screenshots ==

1. Dashboard overview with indexing stats and quick actions
2. Content management tab showing indexed documents
3. Appearance tab with live widget preview
4. Analytics tab showing conversation logs
5. Settings tab with API key configuration
6. Floating bubble mode on the frontend
7. Slide-in panel mode
8. Full-page chat mode

== Changelog ==

= 1.0.0 =
* Initial release
* 4 display modes: bubble, slide-in, embedded, full-page
* Google Gemini embedding + chat integration
* Vector similarity search with cosine distance
* Admin dashboard with analytics
* Auto-indexing on post save
* Gutenberg block + shortcode
* Rate limiting
* Feedback collection (helpful / not helpful)
* Translation ready

== Upgrade Notice ==

= 1.0.0 =
Initial release.
