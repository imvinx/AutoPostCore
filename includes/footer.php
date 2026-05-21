<?php
/**
 * Shared Footer Layout Template
 * YouTube Automation Scheduling Platform
 */
?>
        </main>
    </div>
</div>

<!-- Global Core JS Script -->
<script src="/assets/js/app.js?v=<?= time() ?>"></script>

<!-- Slot for Page-Specific Javascript Declarations -->
<?php if (isset($page_javascript)): ?>
    <script>
        <?= $page_javascript ?>
    </script>
<?php endif; ?>

</body>
</html>
