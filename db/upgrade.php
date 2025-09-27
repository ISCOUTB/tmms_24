<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_tmms_24_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025092321) {
        // Force capability refresh
        accesslib_clear_all_caches_for_unit_testing();
        
        // Update capabilities for all contexts where the block is installed
        $contexts = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.contextlevel, c.instanceid 
            FROM {context} c 
            INNER JOIN {block_instances} bi ON bi.id = c.instanceid 
            WHERE c.contextlevel = ? AND bi.blockname = ?", 
            [CONTEXT_BLOCK, 'tmms_24']
        );
        
        // Also update course contexts that might have the block
        $course_contexts = $DB->get_records_sql("
            SELECT DISTINCT c.id, c.contextlevel, c.instanceid 
            FROM {context} c 
            INNER JOIN {block_instances} bi ON bi.parentcontextid = c.id 
            WHERE c.contextlevel = ? AND bi.blockname = ?", 
            [CONTEXT_COURSE, 'tmms_24']
        );
        
        foreach (array_merge($contexts, $course_contexts) as $context) {
            $context_obj = context::instance_by_id($context->id);
            if ($context_obj) {
                // Clear capability cache for this context
                accesslib_clear_role_cache($context->id);
            }
        }
        
        // Upgrade savepoint reached.
        upgrade_block_savepoint(true, 2025092321, 'tmms_24');
    }

    return true;
}