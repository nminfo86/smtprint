<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Impression automatique | SAIEG</title>
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime('style.css'); ?>">
</head>
<body>
    <header class="navbar">
        <div class="navbar-brand">
            <img src="image/logo.png" alt="Logo" class="logo">
            <span class="brand-title">Impression carte PCBA HEXING</span>
        </div>
        <nav class="navbar-menu">
            <a href="index.php" class="nav-link">🖨️ Scan PCBA</a>
            <a href="batch.php" class="nav-link active">📦 Impression automatique</a>
        </nav>
    </header>

    <main class="container">
        <div class="card">
            <h2>Impression automatique par plage de codes</h2>
            <div class="range-form">
                <div class="range-field">
                    <label for="startInput">De</label>
                    <input type="text" id="startInput" placeholder="Ex: 546200001">
                </div>
                <div class="range-field">
                    <label for="endInput">À</label>
                    <input type="text" id="endInput" placeholder="Ex: 546200100">
                </div>
                <button id="printRangeBtn" class="btn">🖨️ Lancer l'impression</button>
            </div>
        </div>

        <div class="supplier-info" id="supplierDisplay">En attente...</div>
        <div id="progressInfo"></div>
        <div id="status"></div>
    </main>


    <script>
        const startInput = document.getElementById('startInput');
        const endInput = document.getElementById('endInput');
        const printBtn = document.getElementById('printRangeBtn');
        const status = document.getElementById('status');
        const progressInfo = document.getElementById('progressInfo');

        function loadCounter() {
            fetch('get_counter.php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('supplierDisplay').innerText =
                    data.supplierCode + " - " + data.supplier;
            })
            .catch(error => {
                console.error('Erreur chargement compteur:', error);
            });
        }

        window.addEventListener('load', loadCounter);

        printBtn.addEventListener('click', function() {
            const start = startInput.value.trim();
            const end = endInput.value.trim();

            if (!/^\d+$/.test(start) || !/^\d+$/.test(end)) {
                status.innerText = "⚠ ERREUR : Les codes doivent contenir uniquement des chiffres !";
                status.style.color = "orange";
                status.style.fontWeight = "bold";
                return;
            }

            const total = parseInt(end, 10) - parseInt(start, 10) + 1;
            if (total <= 0) {
                status.innerText = "⚠ ERREUR : Le code de fin doit être supérieur ou égal au code de début.";
                status.style.color = "orange";
                status.style.fontWeight = "bold";
                return;
            }

            if (!confirm("Vous allez imprimer " + total + " étiquette(s), de " + start + " à " + end + ". Continuer ?")) {
                return;
            }

            printBtn.disabled = true;
            status.innerText = "Impression en cours...";
            status.style.color = "#333";
            status.style.fontWeight = "normal";
            progressInfo.innerText = "";

            let formData = new FormData();
            formData.append('start', start);
            formData.append('end', end);

            fetch('print_batch.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.supplierCode) {
                    document.getElementById('supplierDisplay').innerText =
                        data.supplierCode + " - " + data.supplier;
                }

                if (data.success) {
                    status.innerText = "✓ " + data.printedCount + " étiquette(s) imprimée(s) avec succès.";
                    status.style.color = "green";
                    progressInfo.innerText = "Codes imprimés : " + data.printedCodes[0] + " → " + data.printedCodes[data.printedCodes.length - 1];
                } else {
                    status.innerText = "✗ Erreur : " + data.error;
                    status.style.color = "red";
                    if (data.printedCount) {
                        progressInfo.innerText = data.printedCount + " étiquette(s) imprimée(s) avant l'erreur.";
                    }
                }
            })
            .catch(error => {
                status.innerText = "Erreur d'impression";
                status.style.color = "red";
                console.error(error);
            })
            .finally(() => {
                printBtn.disabled = false;
            });
        });
    </script>

    <div class="footer">
        Created by <a href="https://www.linkedin.com/in/nassim-bouhezila/" target="_blank">Nminfo</a> © 2026
    </div>
</body>
</html>
