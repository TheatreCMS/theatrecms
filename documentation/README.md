# Theatre CMS

Theatre CMS is a content management system (CMS) designed specifically for the performing arts organization  to create a marketing focused website. While the name implies a focus on theatre (as that is my personal area of expertise), the architecture and terminology is suitable for any organization that provides live performances, such as dance troupes, operas, symphonies, etc.

This documentation is created with and is best viewed using [Obsidian](https://obsidian.md).But the documentation is strictly Markdown files, so use the viewer of your choice.

## Application Architecture

This application is built with three components:

- Core
- Theme
- Plugins

The core contains the main code of the application that is universal to all sites using Theatre CMS. It handles all of the content creation, saving to the database, the administration of the site, etc. _**This should never be customized in your installation, as it will break the update process.**_

The theme section is what drives the visual presentation of the application data. This is where each site can customize the user facing portion of the site. 