-- ============================================================
-- Guised Up — SQL Challenge (Part D)
-- ============================================================

-- D1: Top 10 most active users in the last 7 days, ranked by total interactions
-- (views + replies + reactions combined)
SELECT
    u.id,
    u.name,
    u.email,
    COUNT(i.id) AS total_interactions
FROM users u
JOIN interactions i ON i.user_id = u.id
WHERE i.created_at >= NOW() - INTERVAL '7 days'
GROUP BY u.id, u.name, u.email
ORDER BY total_interactions DESC
LIMIT 10;


-- D2: For a given user_id, return all posts from users they interact with most,
-- ordered by interaction frequency descending, limited to posts from the last 30 days.
-- Replace :user_id with the actual id (or bind as a parameter).
WITH interacted_authors AS (
    SELECT
        p.user_id AS author_id,
        COUNT(*) AS interaction_count
    FROM interactions i
    JOIN posts p ON p.id = i.post_id
    WHERE i.user_id = :user_id
    GROUP BY p.user_id
)
SELECT
    p.id,
    p.text,
    p.user_id AS author_id,
    p.created_at,
    ia.interaction_count
FROM posts p
JOIN interacted_authors ia ON ia.author_id = p.user_id
WHERE p.created_at >= NOW() - INTERVAL '30 days'
ORDER BY ia.interaction_count DESC, p.created_at DESC;


-- D3: Posts viewed more than 100 times but with zero reactions.
SELECT
    p.id AS post_id,
    p.user_id AS author_id,
    COUNT(*) FILTER (WHERE i.type = 'view') AS view_count,
    p.created_at
FROM posts p
JOIN interactions i ON i.post_id = p.id
GROUP BY p.id, p.user_id, p.created_at
HAVING
    COUNT(*) FILTER (WHERE i.type = 'view') > 100
    AND COUNT(*) FILTER (WHERE i.type = 'reaction') = 0;


-- D4: Potential spam detection — users who created more than 20 posts
-- in the last 24 hours. Includes their email and post count.
SELECT
    u.id AS user_id,
    u.email,
    COUNT(p.id) AS post_count
FROM users u
JOIN posts p ON p.user_id = u.id
WHERE p.created_at >= NOW() - INTERVAL '24 hours'
GROUP BY u.id, u.email
HAVING COUNT(p.id) > 20
ORDER BY post_count DESC;
