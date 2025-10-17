<?php
// admin/includes/footer.php
?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js" defer></script>
    
    <?php
    if (isset($page_specific_js)) {
        echo '<script src="' . htmlspecialchars($page_specific_js) . '"></script>';
    }
    ?>
</body>
</html>