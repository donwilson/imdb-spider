SELECT
	entity.*
FROM `entity`
WHERE
	entity.type_id IN ('15')
	AND entity.id NOT IN (
		SELECT entity_meta.entity_id FROM `entity_meta` WHERE entity_meta.meta_id = '22'
	)
LIMIT 30