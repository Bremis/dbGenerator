<?php
require_once __DIR__ . '/classes/DBMenuRenderer.php';
$menuRenderer = new DBMenuRenderer();
?>
<!DOCTYPE html>
<html lang="de">

<head>
  <meta charset="UTF-8">
  <title>Dynamic Component & Entity Loader from DB</title>
  <script src="https://aframe.io/releases/1.6.0/aframe.min.js"></script>
  <script src="https://cdn.jsdelivr.net/gh/donmccurdy/aframe-extras@v6.1.1/dist/aframe-extras.min.js"></script>

  <?php echo $menuRenderer->renderStyles(); ?>

  <script>
    console.log("🧩 VraiheitUI: Core Logic wird geladen...");
    window.VraiheitUI = window.VraiheitUI || {};

    window.VraiheitUI.fetchCompleteSceneFromDB = async function () {
      const resp = await fetch('api.php?action=get_all');
      const data = await resp.json();
      if (data.error) throw new Error(data.error);
      return data;
    };

    window.VraiheitUI.initApp = async function () {
      console.log("🧩 VraiheitUI: initApp gestartet.");
      const sceneEl = document.querySelector('a-scene');
      const menuEl = document.getElementById('ui-menu');
      if (!menuEl) return;

      const items = menuEl.querySelectorAll('.comp-item');
      items.forEach(i => i.remove());

      try {
        const dbPayload = await window.VraiheitUI.fetchCompleteSceneFromDB();
        window.lastPayload = dbPayload;

        dbPayload.components.forEach(comp => {
          if (!AFRAME.components[comp.name]) {
            const script = document.createElement('script');
            script.textContent = comp.source;
            document.head.appendChild(script);
          }
          window.VraiheitUI.addMenuEntry(comp, dbPayload.entities.filter(e => e.componentName === comp.name));
        });

        dbPayload.entities.forEach(entityData => {
          window.VraiheitUI.renderEntity(entityData);
        });
      } catch (err) {
        console.error("Fehler beim Laden:", err);
      }
    };

    window.VraiheitUI.renderEntity = function (data) {
      const sceneEl = document.querySelector('a-scene');
      if (document.getElementById(data.id)) return;

      const newEl = document.createElement('a-entity');
      newEl.setAttribute('id', data.id);
      newEl.setAttribute('position', data.position);

      let attrs = data.style || {};
      if (typeof attrs === 'string') {
        try { attrs = JSON.parse(attrs); } catch (e) { attrs = {}; }
      }
      
      // SICHERHEIT: Falls attrs [] ist, zu {} machen
      if (Array.isArray(attrs)) attrs = {};
      
      newEl.setAttribute(data.componentName, attrs);
      sceneEl.appendChild(newEl);
    };

    window.VraiheitUI.syncTimeout = null;
    window.VraiheitUI.liveUpdate = function (compName, entityId, field, value) {
      const el = document.getElementById(entityId);
      if (!el) return;

      const inst = window.lastPayload.entities.find(e => e.id === entityId);
      if (inst) {
        if (field === 'position') inst.position = value;
        else if (field === 'attributes') {
          try { inst.style = JSON.parse(value); } catch (e) { }
        }
      }

      try {
        if (field === 'position') {
          el.setAttribute('position', value);
        } else if (field === 'attributes') {
          const attrs = JSON.parse(value);
          el.setAttribute(compName, attrs);
        }
      } catch (e) { }

      clearTimeout(window.VraiheitUI.syncTimeout);
      window.VraiheitUI.syncTimeout = setTimeout(() => {
        window.VraiheitUI.saveStateToDB(compName);
      }, 800);
    };

    window.VraiheitUI.saveStateToDB = async function (compName) {
      const compInstances = [];
      document.querySelectorAll(`[${compName}]`).forEach(el => {
        compInstances.push({
          id: el.id,
          position: el.getAttribute('position'),
          attributes: el.getAttribute(compName)
        });
      });

      try {
        await fetch('api.php?action=update_entities', {
          method: 'POST',
          body: JSON.stringify({ component_name: compName, entities: compInstances })
        });
      } catch (err) {
        console.error("Sync-Fehler:", err);
      }
    };

    window.VraiheitUI.addInstance = async function (compName) {
      console.log("🧩 VraiheitUI: Erstelle neue Instanz für " + compName);
      
      const comp = window.lastPayload.components.find(c => c.name === compName);
      if (!comp) return;

      // NEU: Falls Komponente noch nicht in Temps, erst initialisieren!
      if (!comp.is_initialized) {
        console.log(`🧩 VraiheitUI: Komponente ${compName} noch nicht in Temps. Initialisiere...`);
        try {
          const resp = await fetch('api.php?action=initialize_component', {
            method: 'POST',
            body: JSON.stringify({ component_name: compName })
          });
          const res = await resp.json();
          console.log(`🧩 VraiheitUI: ${res.message || 'Initialisierung abgeschlossen'}`);
          comp.is_initialized = true; // Jetzt bereit
        } catch(e) {
          console.error("Fehler bei der Initialisierung:", e);
          alert("Fehler beim Vorbereiten der Komponente im Arbeitsbereich.");
          return;
        }
      }
      
      // --- SCHRITT 1: Vorlage finden ---
      
      // A) Zuerst suchen wir in den bereits in der Szene existierenden Instanzen (Live-Cache)
      const allForComp = window.lastPayload.entities.filter(e => e.componentName === compName);
      let template = allForComp.find(e => e.style && Object.keys(e.style).length > 0 && !Array.isArray(e.style));
      
      let defaultStyle = null;

      if (template) {
          // Wir haben eine Live-Vorlage in der Szene gefunden
          console.log("🧩 VraiheitUI: Vorlage aus Live-Szene gewählt (ID: " + template.id + ")");
          defaultStyle = JSON.parse(JSON.stringify(template.style));
      } else {
          // B) FALLBACK: Keine Instanz in der Szene? Dann schau in die Stamm-Daten (Tabelle aframe_components)
          console.log("🧩 VraiheitUI: Keine Live-Instanz gefunden. Suche in Stamm-Daten...");
          const compDef = window.lastPayload.components.find(c => c.name === compName);
          
          if (compDef && compDef.entities) {
              try {
                  // Die Stamm-Daten liegen als JSON-String vor
                  const masterEntities = typeof compDef.entities === 'string' ? JSON.parse(compDef.entities) : compDef.entities;
                  if (masterEntities && masterEntities.length > 0) {
                      // Nimm die Attribute der ersten Stamm-Instanz
                      // WICHTIG: In der DB heißen die Attribute oft 'attributes', in unserem JS-Objekt 'style'
                      defaultStyle = masterEntities[0].attributes || masterEntities[0].style || {};
                      console.log("🧩 VraiheitUI: Vorlage aus Stamm-Daten (DB) geladen.");
                  }
              } catch(e) {
                  console.error("❌ Fehler beim Parsen der Stamm-Entities:", e);
              }
          }
      }
      
      // Letzte Sicherheit: Falls immer noch nichts gefunden wurde oder es ein Array [] ist
      if (!defaultStyle || Array.isArray(defaultStyle)) {
          defaultStyle = {};
          console.log("🧩 VraiheitUI: Keine Vorlage gefunden, starte mit leerem Objekt {}.");
      }

      // --- SCHRITT 2: Neue Instanz-Daten generieren ---

      const newId = compName + "-" + Math.random().toString(36).substr(2, 5);
      const newInstance = {
        id: newId,
        position: "0 1.6 -3",
        componentName: compName,
        style: defaultStyle
      };
      
      console.log("🧩 VraiheitUI: Finale Attribute für neue Instanz:", newInstance.style);

      // --- SCHRITT 3: In Szene rendern & in DB speichern ---

      window.VraiheitUI.renderEntity(newInstance);

      // Wir sammeln alle aktuellen Instanzen für den DB-Update
      const currentEntities = window.lastPayload.entities
        .filter(e => e.componentName === compName)
        .map(e => ({
          id: e.id,
          position: e.position,
          attributes: e.style
        }));
      
      // Neue Instanz zur Liste hinzufügen
      currentEntities.push({
        id: newInstance.id,
        position: newInstance.position,
        attributes: newInstance.style
      });

      try {
        const resp = await fetch('api.php?action=update_entities', {
          method: 'POST',
          body: JSON.stringify({ component_name: compName, entities: currentEntities })
        });
        const res = await resp.json();
        if (res.status === 'success') {
          // UI neu laden um Counter und Liste zu aktualisieren
          window.VraiheitUI.initApp();
        }
      } catch (err) {
        alert("Fehler beim Speichern in DB: " + err.message);
      }
    };

    window.VraiheitUI.deleteInstance = async function (compName, entityId) {
      if (!confirm(`Instanz ${entityId} wirklich löschen?`)) return;
      const el = document.getElementById(entityId);
      if (el) el.remove();

      const entities = window.lastPayload.entities
        .filter(e => e.componentName === compName && e.id !== entityId)
        .map(e => ({
          id: e.id,
          position: e.position,
          attributes: e.style
        }));

      try {
        const resp = await fetch('api.php?action=update_entities', {
          method: 'POST',
          body: JSON.stringify({ component_name: compName, entities: entities })
        });
        const res = await resp.json();
        if (res.status === 'success') {
          window.VraiheitUI.initApp();
        }
      } catch (err) {
        alert("Fehler beim Löschen: " + err.message);
      }
    };

    window.VraiheitUI.reloadScene = function () {
      const sceneEl = document.querySelector('a-scene');
      sceneEl.querySelectorAll('a-entity[id]').forEach(el => el.remove());
      window.VraiheitUI.initApp();
    };

    window.addEventListener('DOMContentLoaded', () => {
      setTimeout(window.VraiheitUI.initApp, 100);
    });
  </script>
</head>

<body>

  <?php echo $menuRenderer->renderHTML(); ?>

  <a-scene background="color: #0f172a" renderer="colorManagement: true; useLegacyLights: false;"
    cursor="rayOrigin: mouse" raycaster="objects: .clickable">
    <a-entity light="type: ambient; intensity: 0.6"></a-entity>
    <a-entity light="type: directional; intensity: 0.8" position="2 4 3"></a-entity>
    <a-entity camera look-controls position="0 1.6 3"></a-entity>
  </a-scene>

  <?php echo $menuRenderer->renderScripts(); ?>

</body>

</html>