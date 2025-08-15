<aside class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fa-solid fa-cubes"></i> SAC</h3>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li><a href="/SAC/public/dashboard/dashboard.php"><i class="fa-solid fa-gauge"></i>&nbsp;Dashboard</a></li>

            <?php if ($_SESSION['rol'] == 'admin' || $_SESSION['rol'] == 'contabilidad'): ?>
                <li class="nav-section">
                    <a href="#" class="nav-toggle"><i class="fa-solid fa-file-invoice-dollar"></i>&nbsp;Contabilidad</a>
                    <ul class="submenu">
                        <li class="submenu-section">
                            <a href="#" class="submenu-toggle"><i class="fa-solid fa-building-columns"></i>&nbsp;SAT</a>
                            <ul class="sub-submenu">
                                <li><a href="/SAC/public/contabilidad/sat/e-firma.php"><i class="fa-solid fa-key"></i>&nbsp;e.Firma</a></li>
                                <li><a href="/SAC/public/contabilidad/sat/descarga-xml.php"><i class="fa-solid fa-cloud-arrow-down"></i>&nbsp;Descarga XML</a></li>
                                <li><a href="/SAC/public/contabilidad/sat/reportes.php"><i class="fa-solid fa-chart-bar"></i>&nbsp;Reportes</a></li>
                            </ul>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($_SESSION['rol'] == 'admin' || $_SESSION['rol'] == 'operaciones'): ?>
                <li class="nav-section">
                    <a href="#" class="nav-toggle"><i class="fa-solid fa-briefcase"></i>&nbsp;Operaciones</a>
                    <ul class="submenu">
                        <li class="submenu-section">
                            <a href="#" class="submenu-toggle"><i class="fa-solid fa-users"></i>&nbsp;Clientes</a>
                            <ul class="sub-submenu">
                                <li><a href="/SAC/public/operaciones/clientes/reporte-clientes.php"><i class="fa-solid fa-file-lines"></i>&nbsp;Reporte de Clientes</a></li>
                                <li><a href="/SAC/public/operaciones/clientes/admin-clientes.php"><i class="fa-solid fa-user-gear"></i>&nbsp;Admin Clientes</a></li>
                            </ul>
                        </li>
                        <li class="submenu-section">
                            <a href="#" class="submenu-toggle"><i class="fa-solid fa-building"></i>&nbsp;Buró de Crédito</a>
                            <ul class="sub-submenu">
                                <li><a href="/SAC/public/operaciones/buro-credito/tablas-amortizacion.php"><i class="fa-solid fa-table"></i>&nbsp;Tablas de Amortización</a></li>
                                <li><a href="/SAC/public/operaciones/buro-credito/macros.php"><i class="fa-solid fa-cogs"></i>&nbsp;Macros</a></li>
                            </ul>
                        </li>
                        <li><a href="/SAC/public/operaciones/ribc/ribc.php"><i class="fa-solid fa-coins"></i>&nbsp;RIBC</a></li>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($_SESSION['rol'] == 'admin' || $_SESSION['rol'] == 'hr'): ?>
                <li class="nav-section">
                    <a href="#" class="nav-toggle"><i class="fa-solid fa-user-tie"></i>&nbsp;RH</a>
                    <ul class="submenu">
                        <li><a href="/SAC/public/rh/solicitudes/solicitudes.php"><i class="fa-solid fa-envelope-open-text"></i>&nbsp;Solicitudes</a></li>
                        <li><a href="/SAC/public/rh/horarios/horarios.php"><i class="fa-solid fa-clock"></i>&nbsp;Horarios</a></li>
                    </ul>
                </li>
            <?php endif; ?>

            <?php if ($_SESSION['rol'] == 'admin'): ?>
                <li class="nav-section">
                    <a href="#" class="nav-toggle"><i class="fa-solid fa-microchip"></i>&nbsp;IT</a>
                    <ul class="submenu">
                        <li><a href="/SAC/public/it/sistemas/sistemas.php"><i class="fa-solid fa-server"></i>&nbsp;Sistemas</a></li>
                        <li><a href="/SAC/public/it/usuarios/usuarios.php"><i class="fa-solid fa-users-gear"></i>&nbsp;Usuarios</a></li>
                    </ul>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</aside>