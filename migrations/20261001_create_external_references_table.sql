-- Links records in external systems (WordPress, Tessitura, ...) to TheatreCMS content, so imports
-- and syncs can re-run without creating duplicates. Mapped by src/Models/ExternalReference.php;
-- see documentation/external-references.md.
--
-- content_id is a soft reference with no foreign key (like content_meta.content_id), so deleting
-- content doesn't delete its references.
CREATE TABLE external_references (
    id INT AUTO_INCREMENT NOT NULL,
    source VARCHAR(32) NOT NULL,
    source_type VARCHAR(64) NOT NULL,
    source_id VARCHAR(191) NOT NULL,
    content_type VARCHAR(32) NOT NULL,
    content_id INT NOT NULL,
    synced_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_external_references_content (content_type, content_id),
    UNIQUE INDEX uniq_external_references_source (source, source_type, source_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4;
