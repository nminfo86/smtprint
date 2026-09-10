<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Scan PCBA | SAIEG</title>
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime('style.css'); ?>">
</head>
<body>
    <header class="navbar">
        <div class="navbar-brand">
            <img src="image/logo.png" alt="Logo" class="logo">
            <span class="brand-title">Impression étiquette carte PCBA</span>
        </div>
        <nav class="navbar-menu">
            <a href="index.php" class="nav-link active">🖨️ Scan PCBA</a>
            <a href="batch.php" class="nav-link">📦 Impression automatique</a>
        </nav>
    </header>

    <main class="container">
        <div class="card">
            <h2>Scanner le code du PCBA</h2>
            <!-- L'attribut autofocus est crucial pour la douchette -->
            <input type="text" id="scanInput" autofocus placeholder="En attente du scan...">
        </div>

        <div class="counters-grid">
            <div class="counter">
                <div>Compteur journalier</div>
                <div class="counter-value" id="counterDisplay">0000</div>
            </div>
            <div class="counter">
                <div>Compteur mensuel</div>
                <div class="counter-value" id="monthlyDisplay">00000</div>
            </div>
            <div class="counter">
                <div>Compteur annuel</div>
                <div class="counter-value" id="annualDisplay">000000</div>
            </div>
        </div>
        <div class="supplier-info" id="supplierDisplay">En attente...</div>
        <div id="status"></div>
    </main>


    <script>
        const input = document.getElementById('scanInput');
        const status = document.getElementById('status');

        // Charger le compteur au démarrage de la page
        function loadCounter() {
            fetch('get_counter.php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('counterDisplay').innerText = data.counter;
                document.getElementById('monthlyDisplay').innerText = data.monthlyCounter;
                document.getElementById('annualDisplay').innerText = data.annualCounter;
                document.getElementById('supplierDisplay').innerText = 
                    data.supplierCode + " - " + data.supplier;
            })
            .catch(error => {
                console.error('Erreur chargement compteur:', error);
            });
        }

        // Charger le compteur au chargement de la page
        window.addEventListener('load', loadCounter);

        input.addEventListener('keypress', function(e) {
            // La douchette simule la touche Entrée (Code 13)
            if (e.key === 'Enter') {
                e.preventDefault();
                let code = input.value;
                
                if(code.trim() !== "") {
                    imprimerCode(code);
                }
                
                // Vider le champ pour le scan suivant
                input.value = '';
            }
        });

        function imprimerCode(code) {
            // Vérifier que le code contient uniquement des chiffres
            if (!/^\d+$/.test(code)) {
                status.innerText = "⚠ ERREUR : Le code doit contenir uniquement des chiffres ! Vérifiez que la langue de saisie est bien en anglais.";
                status.style.color = "orange";
                status.style.fontWeight = "bold";
                return; // Ne pas continuer si le code n'est pas valide
            }
            
            status.innerText = "Impression en cours...";
            status.style.color = "#333";
            status.style.fontWeight = "normal";
            
            // Envoi des données au backend PHP
            let formData = new FormData();
            formData.append('code_pcba', code);

            fetch('print.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('counterDisplay').innerText = data.counter;
                    document.getElementById('monthlyDisplay').innerText = data.monthlyCounter;
                    document.getElementById('annualDisplay').innerText = data.annualCounter;
                    document.getElementById('supplierDisplay').innerText = 
                        data.supplierCode + " - " + data.supplier;
                    status.innerText = "✓ Dernier scan : " + code + " (Imprimé)";
                    status.style.color = "green";
                } else {
                    status.innerText = "✗ Erreur : " + data.error;
                    status.style.color = "red";
                }
            })
            .catch(error => {
                status.innerText = "Erreur d'impression";
                status.style.color = "red";
                console.error(error);
            });
        }
    </script>

    <div class="footer">
        Created by <a href="https://www.linkedin.com/in/nassim-bouhezila/" target="_blank">Nminfo</a> © 2026
    </div>
</body>
</html>