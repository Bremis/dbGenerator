<?php

class DBMenuRenderer {
    /**
     * Liefert das CSS für das Menü und den Button.
     */
    public function renderStyles() {
        return '
        <style>
            #ui-toggle {
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1000;
                background: #1e293b;
                color: #fff;
                border: 1px solid #334155;
                border-radius: 8px;
                width: 44px;
                height: 44px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            #ui-toggle:hover {
                background: #334155;
                transform: scale(1.05);
            }
            #ui-menu {
                position: fixed;
                top: 0;
                left: -320px;
                width: 300px;
                height: 100vh;
                background: rgba(15, 23, 42, 0.95);
                backdrop-filter: blur(10px);
                z-index: 999;
                box-shadow: 4px 0 20px rgba(0,0,0,0.5);
                padding: 80px 20px 20px;
                transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                color: #e2e8f0;
                overflow-y: auto;
                border-right: 1px solid #334155;
            }
            #ui-menu.open {
                left: 0;
            }
            .comp-item {
                margin-bottom: 10px;
                border: 1px solid #334155;
                border-radius: 6px;
                overflow: hidden;
                background: rgba(30, 41, 59, 0.5);
            }
            .comp-header {
                padding: 10px 15px;
                background: #1e293b;
                cursor: pointer;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-weight: bold;
                font-size: 0.9rem;
            }
            .comp-header:hover {
                background: #334155;
            }
            .comp-content {
                padding: 12px;
                background: #0f172a;
                font-size: 0.8rem;
                border-top: 1px solid #334155;
            }
            .tag-badge {
                background: #0ea5e9;
                color: #fff;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 10px;
                text-transform: uppercase;
            }
        </style>';
    }

    /**
     * Liefert die HTML-Struktur des Menüs.
     */
    public function renderHTML() {
        return '
        <button id="ui-toggle" onclick="if(window.VraiheitUI && window.VraiheitUI.toggleMenu) { window.VraiheitUI.toggleMenu(); } else { console.error(\'VraiheitUI.toggleMenu nicht bereit!\'); }" title="Komponenten-Menü">🧩</button>
        <div id="ui-menu">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #334155; padding-bottom:10px; margin-bottom:15px;">
                <h3 style="margin:0; color:#38bdf8;">Vraiheit Assets</h3>
                <button onclick="if(window.VraiheitUI && window.VraiheitUI.reloadScene) window.VraiheitUI.reloadScene()" title="Neu laden" style="background:none; border:none; color:#94a3b8; cursor:pointer;">🔄</button>
            </div>
            <!-- Dynamischer Content -->
        </div>';
    }

    /**
     * Liefert die UI-JavaScript Funktionen für das Menü.
     */
    public function renderScripts() {
        return '
        <script>
            console.log("🧩 VraiheitUI-Menu: Start Ladevorgang...");
            
            window.VraiheitUI = window.VraiheitUI || {};
            window.VraiheitUI.openComponents = new Set();

            window.VraiheitUI.toggleMenu = function() {
                const menu = document.getElementById("ui-menu");
                if (!menu) return;
                menu.classList.toggle("open");
            };

            window.VraiheitUI.toggleCompContent = function(header, name) {
                const content = header.nextElementSibling;
                const isVisible = content.style.display === "block";
                content.style.display = isVisible ? "none" : "block";
                
                if (isVisible) window.VraiheitUI.openComponents.delete(name);
                else window.VraiheitUI.openComponents.add(name);
            };

            window.VraiheitUI.addMenuEntry = function(comp, instances) {
                const menu = document.getElementById("ui-menu");
                if (!menu) return;
                const item = document.createElement("div");
                item.className = "comp-item";
                
                const isOpen = window.VraiheitUI.openComponents.has(comp.name);
                
                item.innerHTML = `
                    <div class="comp-header" onclick="window.VraiheitUI.toggleCompContent(this, \'${comp.name}\')">
                        <span>🧩 ${comp.name}</span>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <button onclick="event.stopPropagation(); window.VraiheitUI.addInstance(\'${comp.name}\')" title="Neue Instanz hinzufügen" style="background:#10b981; border:none; color:#fff; width:20px; height:20px; border-radius:50%; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center;">+</button>
                            <span class="tag-badge">${instances.length}</span>
                        </div>
                    </div>
                    <div class="comp-content" style="display: ${isOpen ? "block" : "none"}">
                        <div style="color:#94a3b8; margin-bottom:8px; font-size:0.7rem;">INSTANZEN & PROPERTIES:</div>
                        <div id="inst-list-${comp.name}">
                            ${instances.map(inst => `
                                <div class="inst-editor" style="background:rgba(0,0,0,0.3); border-radius:6px; padding:8px; margin-bottom:10px; border:1px solid #334155;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                        <strong style="color:#38bdf8; font-size:0.75rem;">ID: ${inst.id}</strong>
                                        <button onclick="window.VraiheitUI.deleteInstance(\'${comp.name}\', \'${inst.id}\')" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:12px;">🗑️</button>
                                    </div>
                                    
                                    <div style="margin-bottom:6px;">
                                        <label style="display:block; font-size:10px; color:#94a3b8;">Position:</label>
                                        <input type="text" value="${inst.position}" 
                                            oninput="window.VraiheitUI.liveUpdate(\'${comp.name}\', \'${inst.id}\', \'position\', this.value)"
                                            style="width:100%; background:#0f172a; border:1px solid #1e293b; color:#fff; font-size:11px; padding:3px 6px; border-radius:3px;">
                                    </div>

                                    <div>
                                        <label style="display:block; font-size:10px; color:#94a3b8;">Attributes (JSON):</label>
                                        <textarea oninput="window.VraiheitUI.liveUpdate(\'${comp.name}\', \'${inst.id}\', \'attributes\', this.value)"
                                            style="width:100%; height:40px; background:#0f172a; border:1px solid #1e293b; color:#10b981; font-size:11px; padding:3px 6px; border-radius:3px; font-family:monospace; resize:vertical;">${JSON.stringify(inst.style)}</textarea>
                                    </div>
                                </div>
                            `).join("")}
                        </div>
                        <button onclick="window.VraiheitUI.reloadScene()" style="width:100%; margin-top:5px; padding:6px; background:#334155; border:none; color:#fff; border-radius:4px; cursor:pointer; font-size:10px; font-weight:bold;">🔄 SZENE REFRESH</button>
                    </div>
                `;
                menu.appendChild(item);
            };

            console.log("🧩 VraiheitUI-Menu: Alle Funktionen geladen.");
        </script>';
    }
}
