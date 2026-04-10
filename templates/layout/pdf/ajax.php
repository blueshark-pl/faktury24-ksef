<?php
// Pass-through layout dla CakePdf — szablon print_custom.php jest standalone
// (ma własny <html>/<body>), więc layout nie dokłada nic.
echo $this->fetch('content');
