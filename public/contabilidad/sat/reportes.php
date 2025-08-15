<?php
session_start();
require_once '../../../src/helpers/auth.php';
checkAuth(['admin', 'contabilidad']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - SAC</title>
    <link rel="stylesheet" href="../../dashboard/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../../src/views/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../../../src/views/header.php'; ?>

            <div class="content">
                <div class="toolbar">
                    <h1>Reportes</h1>
                </div>

                <div class="card-container">
                    <div class="report-card" id="rfc-botl-card">
                        <div class="card-icon">
                            <i class="fa-solid fa-file-excel" style="font-size:2.8rem;color:#217346;"></i>
                        </div>
                        <h2>Reporte RFC BOTL</h2>
                    </div>
                    <div class="report-card">
                        <div class="card-icon"><span>🔎</span></div>
                        <h2>Buscar por 3ros</h2>
                    </div>
                    <div class="report-card">
                        <div class="card-icon"><span>📄</span></div>
                        <h2>Buscar por Folio Fiscal</h2>
                    </div>
                    <div class="report-card">
                        <div class="card-icon"><span>✨</span></div>
                        <h2>Reportes especiales</h2>
                    </div>
                </div>
                <style>
                    .card-container {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        gap: 2rem;
                        margin-top: 4rem;
                        flex-wrap: wrap;
                    }

                    .report-card {
                        background: #fff;
                        border-radius: 18px;
                        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
                        padding: 2.5rem 2rem 2rem 2rem;
                        min-width: 220px;
                        max-width: 260px;
                        text-align: center;
                        transition: transform 0.2s cubic-bezier(.4, 2, .3, .7), box-shadow 0.2s;
                        cursor: pointer;
                        border: 1px solid #eaeaea;
                    }

                    .report-card:hover {
                        transform: translateY(-8px) scale(1.04);
                        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.14);
                        border-color: #b3d4fc;
                    }

                    .card-icon {
                        font-size: 2.8rem;
                        margin-bottom: 1rem;
                    }

                    .report-card h2 {
                        font-size: 1.15rem;
                        font-weight: 600;
                        margin: 0;
                        color: #2a2a2a;
                    }
                </style>
            </div>
        </main>
    </div>

    <script src="../../dashboard/dashboard.js"></script>
    <script>
        document.getElementById('rfc-botl-card').addEventListener('click', function() {
            window.location.href = '/SAC/public/dashboard/reportes_rfc_bolt.php';
        });
    </script>
</body>

</html>