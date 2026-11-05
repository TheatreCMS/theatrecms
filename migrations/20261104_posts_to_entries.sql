-- Posts become entries of the `post` content type (THE-154): they move from `posts` into the
-- shared `entries` table and are served by the generic EntryController. Every reference to a
-- post is re-pointed at its new entry id, then `posts` is dropped.
--
-- Posts get new ids, so a reference can't just keep its number: post 3 may become entry 7 while
-- another post becomes entry 3. post_entry_ids maps old to new. Rows keyed by (content_type,
-- content_id) with a unique index are moved in two passes, through a temporary 'post-migrating'
-- type, so no row ever collides with one that hasn't been moved yet.
--
-- References to posts that no longer exist would otherwise land on whichever entry inherits
-- their old number, so they are removed instead: orphaned meta, term links and external
-- references are deleted, and a menu item linking to a deleted post becomes an empty custom
-- link, which renders nothing, as before.
--
-- Empty post content becomes NULL, matching other entries. Posts had no hierarchy.

CREATE TABLE post_entry_ids (
    post_id INT NOT NULL,
    entry_id INT NOT NULL,
    PRIMARY KEY (post_id)
) DEFAULT CHARACTER SET utf8mb4;

INSERT INTO entries (content_type, title, slug, content, excerpt, status, published_at, created_at, modified_at, featured_image_id)
SELECT 'post', title, slug, NULLIF(content, ''), excerpt, status, published_at, created_at, modified_at, featured_image_id
FROM posts
ORDER BY id;

-- Post slugs were unique across posts, and entry slugs are unique per type, so the slug joins them.
INSERT INTO post_entry_ids (post_id, entry_id)
SELECT p.id, e.id
FROM posts p
JOIN entries e ON e.content_type = 'post' AND e.slug = p.slug;

-- content_meta: unique on (content_type, content_id, meta_key).
UPDATE content_meta cm
JOIN post_entry_ids m ON cm.content_type = 'post' AND cm.content_id = m.post_id
SET cm.content_type = 'post-migrating', cm.content_id = m.entry_id;
DELETE FROM content_meta WHERE content_type = 'post';
UPDATE content_meta SET content_type = 'post' WHERE content_type = 'post-migrating';

-- term_relationships: unique on (term_id, content_type, content_id).
UPDATE term_relationships tr
JOIN post_entry_ids m ON tr.content_type = 'post' AND tr.content_id = m.post_id
SET tr.content_type = 'post-migrating', tr.content_id = m.entry_id;
DELETE FROM term_relationships WHERE content_type = 'post';
UPDATE term_relationships SET content_type = 'post' WHERE content_type = 'post-migrating';

-- external_references: no unique index on the content columns, but the same passes keep
-- orphans from inheriting a new entry's id.
UPDATE external_references er
JOIN post_entry_ids m ON er.content_type = 'post' AND er.content_id = m.post_id
SET er.content_type = 'post-migrating', er.content_id = m.entry_id;
DELETE FROM external_references WHERE content_type = 'post';
UPDATE external_references SET content_type = 'post' WHERE content_type = 'post-migrating';

-- menu_items: link_type 'post' with a target links one post, and without one the posts archive,
-- which needs no change.
UPDATE menu_items mi
JOIN post_entry_ids m ON mi.link_type = 'post' AND mi.target_id = m.post_id
SET mi.link_type = 'post-migrating', mi.target_id = m.entry_id;
UPDATE menu_items SET link_type = 'custom', target_id = NULL, custom_url = NULL
WHERE link_type = 'post' AND target_id IS NOT NULL;
UPDATE menu_items SET link_type = 'post' WHERE link_type = 'post-migrating';

DROP TABLE post_entry_ids;
DROP TABLE posts;
