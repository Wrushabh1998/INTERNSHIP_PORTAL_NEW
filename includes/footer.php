<?php
/**
 * Shared HTML Footer
 * Include at the BOTTOM of every page (before </body>)
 */
?>
<!-- Main JS -->
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<?php if (!empty($extraJS)): foreach ($extraJS as $js): ?>
<script src="<?= SITE_URL ?>/assets/js/<?= $js ?>"></script>
<?php endforeach; endif; ?>

<?php if (!empty($inlineJS)): ?>
<script><?= $inlineJS ?></script>
<?php endif; ?>

</body>
</html>
