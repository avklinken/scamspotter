SET NAMES utf8mb4;

UPDATE sources
SET feed_url = 'https://www.fraudehelpdesk.nl/feed/?post_type=alert',
    source_type = 'rss',
    crawl_method = 'rss',
    notes = 'Nederlandse meldingen en waarschuwingen over fraude; nieuwe items gaan eerst naar de redactionele review-queue.'
WHERE slug = 'fraudehelpdesk';
