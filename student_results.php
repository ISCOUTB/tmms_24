<div class="tmms-results-summary">
    <h4><?php echo get_string('your_results', 'block_tmms_24'); ?></h4>
    <ul>
        <li><strong><?php echo get_string('perception', 'block_tmms_24'); ?>:</strong> <?php echo $scores['percepcion']; ?> (<?php echo $interpretations['result']['percepcion']; ?>)</li>
        <li><strong><?php echo get_string('comprehension', 'block_tmms_24'); ?>:</strong> <?php echo $scores['comprension']; ?> (<?php echo $interpretations['result']['comprension']; ?>)</li>
        <li><strong><?php echo get_string('regulation', 'block_tmms_24'); ?>:</strong> <?php echo $scores['regulacion']; ?> (<?php echo $interpretations['result']['regulacion']; ?>)</li>
    </ul>
    <a href="<?php echo new moodle_url('/blocks/tmms_24/view.php', ['cid' => $COURSE->id, 'view_results' => 1]); ?>" class="btn btn-link"><?php echo get_string('view_detailed_results', 'block_tmms_24'); ?></a>
</div>
