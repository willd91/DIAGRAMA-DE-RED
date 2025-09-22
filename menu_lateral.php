<?php
// VISTAS/CVsecurity/menu_lateral.php
?>
<div id="sidebarMenu" class="cvsecurity-sidebar">
    <div class="cvsecurity-sidebar-header">
        <h5 class="cvsecurity-sidebar-title">
            <i class="bi bi-shield-lock-fill me-2"></i>UNIFI
        </h5>
    </div>
    <nav class="cvsecurity-sidebar-nav nav flex-column mt-2">
        <div class="cvsecurity-sidebar-divider my-2"></div>
        
        <div class="cvsecurity-sidebar-section">
            <h6 class="cvsecurity-sidebar-subtitle px-3 mb-2">
                <i class="bi bi-wifi me-2"></i>UniFi Network
            </h6>
            <a class="cvsecurity-sidebar-link nav-link d-flex align-items-center" href="/inventario_ips/VISTAS/UNIFI/view_clientes_unifi.php">
                <i class="bi bi-people-fill me-2"></i> Clientes Conectados
            </a>
            <a class="cvsecurity-sidebar-link nav-link d-flex align-items-center" href="/inventario_ips/VISTAS/UNIFI/view_dispositivos_unifi.php">
                <i class="bi bi-hdd-stack me-2"></i> Dispositivos UniFi
            </a>
        </div>
    </nav>
</div>

<style>
.cvsecurity-sidebar-divider {
    border-top: 1px solid #dee2e6;
    opacity: 0.3;
}

.cvsecurity-sidebar-section {
    margin-top: 0.5rem;
}

.cvsecurity-sidebar-subtitle {
    font-size: 0.8rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>