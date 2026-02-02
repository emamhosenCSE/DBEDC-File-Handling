<?php
$content = file_get_contents('sql/migration_v2.sql');
$content = preg_replace('/^DELIMITER.*$/im', '', $content);
$content = preg_replace('/END\s*\/\//i', 'END;', $content);
file_put_contents('sql/migration_v2_clean.sql', $content);
echo 'Cleaned migration file created: sql/migration_v2_clean.sql';
?>