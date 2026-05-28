-- Fix admin Capung missing position_id.
-- Dashboard joins users -> position -> branch, so admin users must have a position.
-- position_id 1 = ADM / ADMIN, branch SDR.

UPDATE users
SET position_id = 1
WHERE email = 'capungaero@gmail.com'
  AND (position_id IS NULL OR position_id = 0);

SELECT
  users.id,
  users.email,
  users.position_id,
  `position`.position_code,
  `position`.position_name,
  `position`.branch_id,
  branch.branch_code
FROM users
LEFT JOIN `position` ON `position`.id = users.position_id
LEFT JOIN branch ON branch.id = `position`.branch_id
WHERE users.email = 'capungaero@gmail.com';
