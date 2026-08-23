import{j as e,a as y,p as a,b as C,e as H,u as Fe,c as re,h as N,d as P,_ as je,$ as we,a0 as ke,al as Ce,a1 as ee,t as _e,s as Je}from"./vendor-radix-DY8pb31i.js";import{R as U,a as c}from"./vendor-inertia-BheeDqvO.js";import"./logRange-CUdWC1SU.js";import"./useObjectionsListState-FGnx5z52.js";import{P as Xe}from"./ObjectionsStatsSection-CLP5dmif.js";import{m as Y,c as Q,d as ze,M as Se,g as J,G as ce,_ as te,L as Ke,s as ae,ad as se,av as ge,R as de,aw as er,ax as pe,ab as rr,b as tr,ah as he,$ as ue,p as or}from"./react-icons.esm-BoLhkdvp.js";import{L as M}from"./leaflet-GjjsV4zE.js";import{d as fe,M as nr,T as ir}from"./TileLayer-DAqEQq-9.js";import"./DepartmentForm-N7AyyY4k.js";import"./ErrorBoundary-Dl4rVtSl.js";import"./MonthlyCalendarTab-DnnRbtUa.js";import"./index.esm-MmCp14hd.js";import"./firebase-config-AUwd4BOu.js";import"./vendor-utils-Bd_1ICpc.js";const oe={voyager:{id:"voyager",name:"Voyager (Crisp Light)",icon:"Compass",url:"https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png",subdomains:"abcd",maxZoom:20,attribution:'&copy; <a href="https://carto.com/">CARTO</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'},darkMatter:{id:"darkMatter",name:"Dark Matter (Midnight)",icon:"Moon",url:"https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png",subdomains:"abcd",maxZoom:20,attribution:'&copy; <a href="https://carto.com/">CARTO</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'},positron:{id:"positron",name:"Positron (Minimal Light)",icon:"Sun",url:"https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png",subdomains:"abcd",maxZoom:20,attribution:'&copy; <a href="https://carto.com/">CARTO</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'},satellite:{id:"satellite",name:"Satellite (Aerial HD)",icon:"Globe",url:"https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",subdomains:"",maxZoom:19,attribution:"Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community"},osm:{id:"osm",name:"OpenStreetMap Standard",icon:"Map",url:"https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",subdomains:"abc",maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'}},ar=[23.8103,90.4125],sr=12,lr=7,cr=19,le=15,A={active:"#10b981",completed:"#3b82f6",punchin:"#10b981",punchout:"#ef4444"},dr=`
/* Living Radar Pulse Keyframes */
@keyframes radarPing {
    0% {
        transform: scale(0.7);
        opacity: 0.9;
    }
    50% {
        opacity: 0.5;
    }
    100% {
        transform: scale(2.2);
        opacity: 0;
    }
}

@keyframes beaconGlow {
    0%, 100% {
        box-shadow: 0 0 10px rgba(16, 185, 129, 0.6), 0 0 20px rgba(16, 185, 129, 0.3);
    }
    50% {
        box-shadow: 0 0 18px rgba(16, 185, 129, 0.9), 0 0 32px rgba(16, 185, 129, 0.5);
    }
}

@keyframes dashFlow {
    to {
        stroke-dashoffset: -24;
    }
}

/* Custom Marker Classes */
.living-marker-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.living-marker-wrapper:hover {
    transform: scale(1.15) translateY(-3px);
    z-index: 9999 !important;
}

.living-marker-radar-ring {
    position: absolute;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(16, 185, 129, 0.25);
    border: 1.5px solid rgba(16, 185, 129, 0.85);
    animation: radarPing 2.2s cubic-bezier(0, 0.2, 0.8, 1) infinite;
    pointer-events: none;
}

.living-marker-core {
    position: relative;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 2.5px solid var(--color-surface, #ffffff);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: linear-gradient(135deg, var(--blue-9, #2563eb), var(--blue-11, #1e40af));
    color: #ffffff;
    font-weight: 700;
    font-size: 13px;
    z-index: 2;
}

.living-marker-core.is-active {
    border-color: #10b981;
    background: linear-gradient(135deg, #10b981, #047857);
    animation: beaconGlow 2.5s ease-in-out infinite;
}

.living-marker-core.is-completed {
    border-color: var(--gray-8, #94a3b8);
    background: linear-gradient(135deg, var(--gray-9, #64748b), var(--gray-11, #334155));
}

.living-marker-core.is-punchin {
    border-color: #10b981;
    background: linear-gradient(135deg, #10b981, #059669);
}

.living-marker-core.is-punchout {
    border-color: #ef4444;
    background: linear-gradient(135deg, #ef4444, #b91c1c);
}

.living-marker-badge {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid var(--color-surface, #ffffff);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    color: #ffffff;
    z-index: 3;
}

/* Radix Theme-Aware Leaflet Popup */
.leaflet-popup-content-wrapper {
    background: transparent !important;
    box-shadow: none !important;
    padding: 0 !important;
    border-radius: var(--radius-4, 12px) !important;
}

.leaflet-popup-content {
    margin: 0 !important;
    line-height: normal !important;
}

.leaflet-popup-tip {
    background: var(--color-panel-solid, var(--color-surface, #ffffff)) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

/* Centroid Labels for Polygons */
.geofence-centroid-badge {
    background: var(--color-panel-solid, var(--color-surface, #ffffff));
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--gray-a6);
    border-radius: 20px;
    padding: 3px 10px;
    color: var(--gray-12, #1e293b);
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Trajectory flowing dashes */
.patrol-trajectory-path {
    stroke-dasharray: 8 6;
    animation: dashFlow 1.2s linear infinite;
}
`,Le=U.memo(({stats:t,lastUpdateText:s,isPolling:h,secondsLeft:j})=>{const f=(t==null?void 0:t.total)||0,F=(t==null?void 0:t.checkedIn)??(t==null?void 0:t.active)??0,d=(t==null?void 0:t.completed)||0,l=f>0?Math.round(F/f*100):0;return e.jsx(y,{p:"3",style:{background:"linear-gradient(135deg, var(--gray-a2), var(--gray-a3))",borderBottom:"1px solid var(--gray-a4)"},children:e.jsxs(a,{justify:"between",align:"center",gap:"3",wrap:"wrap",children:[e.jsxs(a,{align:"center",gap:"3",wrap:"wrap",children:[e.jsxs(a,{align:"center",gap:"2",px:"3",py:"2",style:{borderRadius:"var(--radius-3)",background:"var(--color-panel-solid, #ffffff)",border:"1px solid var(--gray-a4)",boxShadow:"0 1px 3px rgba(0,0,0,0.05)"},children:[e.jsx(a,{align:"center",justify:"center",style:{width:28,height:28,borderRadius:"50%",background:"var(--blue-a3)",color:"var(--blue-9)"},children:e.jsx(Y,{style:{width:16,height:16}})}),e.jsxs(y,{children:[e.jsxs(a,{align:"baseline",gap:"1",children:[e.jsx(C,{size:"4",weight:"bold",style:{color:"var(--gray-12)"},children:f}),e.jsx(C,{size:"1",color:"gray",children:"Officers"})]}),e.jsx(C,{size:"1",color:"gray",style:{fontSize:10,display:"block",marginTop:-2},children:"Total Tracked"})]})]}),e.jsxs(a,{align:"center",gap:"2",px:"3",py:"2",style:{borderRadius:"var(--radius-3)",background:"var(--color-panel-solid, #ffffff)",border:"1px solid var(--green-a5)",boxShadow:"0 1px 3px rgba(0,0,0,0.05)"},children:[e.jsxs(a,{align:"center",justify:"center",style:{width:28,height:28,borderRadius:"50%",background:"var(--green-a3)",color:"var(--green-9)",position:"relative"},children:[e.jsx(Q,{style:{width:16,height:16}}),F>0&&e.jsx("span",{style:{position:"absolute",top:1,right:1,width:8,height:8,borderRadius:"50%",background:A.active,border:"1.5px solid white"}})]}),e.jsxs(y,{children:[e.jsxs(a,{align:"baseline",gap:"1",children:[e.jsx(C,{size:"4",weight:"bold",style:{color:"var(--green-11)"},children:F}),e.jsxs(H,{size:"1",color:"green",variant:"soft",radius:"full",children:[l,"%"]})]}),e.jsx(C,{size:"1",color:"gray",style:{fontSize:10,display:"block",marginTop:-2},children:"Active On-Duty"})]})]}),e.jsxs(a,{align:"center",gap:"2",px:"3",py:"2",style:{borderRadius:"var(--radius-3)",background:"var(--color-panel-solid, #ffffff)",border:"1px solid var(--gray-a4)",boxShadow:"0 1px 3px rgba(0,0,0,0.05)"},children:[e.jsx(a,{align:"center",justify:"center",style:{width:28,height:28,borderRadius:"50%",background:"var(--blue-a3)",color:"var(--blue-9)"},children:e.jsx(ze,{style:{width:16,height:16}})}),e.jsxs(y,{children:[e.jsxs(a,{align:"baseline",gap:"1",children:[e.jsx(C,{size:"4",weight:"bold",style:{color:"var(--blue-11)"},children:d}),e.jsx(C,{size:"1",color:"gray",children:"Completed"})]}),e.jsx(C,{size:"1",color:"gray",style:{fontSize:10,display:"block",marginTop:-2},children:"Finished Shifts"})]})]})]}),e.jsxs(a,{align:"center",gap:"2",children:[e.jsxs(a,{align:"center",gap:"2",px:"2",py:"1",style:{background:"var(--gray-a3)",borderRadius:"var(--radius-2)",border:"1px solid var(--gray-a4)"},children:[e.jsx(a,{align:"center",justify:"center",style:{width:8,height:8,borderRadius:"50%",background:h?A.active:"var(--gray-8)",boxShadow:h?"0 0 8px #10b981":"none"}}),e.jsx(C,{size:"1",color:"gray",children:h?`Live Sync (${j}s)`:"Polling Paused"})]}),s&&e.jsxs(C,{size:"1",color:"gray",style:{fontSize:11},children:["Updated: ",s]})]})]})})});Le.displayName="MapStatsRibbon";const Ie=U.memo(({searchQuery:t,onSearchChange:s,statusFilter:h,onStatusFilterChange:j,stats:f,currentTileId:F,onTileChange:d,layerVisibility:l,onToggleLayer:b,onFitBounds:m,onRefresh:o,isRefreshing:v,isDrawerOpen:n,onToggleDrawer:i,isFullscreen:u,onToggleFullscreen:r})=>{var x;return e.jsx(y,{style:{position:"absolute",top:14,left:14,right:14,zIndex:1e3,pointerEvents:"none"},children:e.jsxs(a,{gap:"2",align:"center",justify:"between",wrap:"wrap",style:{pointerEvents:"auto"},children:[e.jsxs(a,{align:"center",gap:"2",wrap:"wrap",p:"2",style:{background:"var(--color-surface)",backdropFilter:"blur(16px)",WebkitBackdropFilter:"blur(16px)",borderRadius:"var(--radius-4)",border:"1px solid var(--gray-a5)",boxShadow:"var(--shadow-4, 0 8px 30px rgba(0, 0, 0, 0.12))"},children:[e.jsx(y,{style:{width:190},children:e.jsxs(Fe,{size:"1",variant:"surface",placeholder:"Search officer / ID...",value:t,onChange:g=>s(g.target.value),children:[e.jsx(re,{children:e.jsx(Se,{style:{color:"var(--gray-9)"}})}),t&&e.jsx(re,{children:e.jsx(N,{size:"1",variant:"ghost",color:"gray",style:{cursor:"pointer"},onClick:()=>s(""),children:e.jsx(J,{})})})]})}),e.jsxs(a,{align:"center",gap:"1",children:[e.jsxs(P,{size:"1",variant:h==="all"?"solid":"soft",color:"gray",onClick:()=>j("all"),style:{cursor:"pointer",fontWeight:600},children:["All (",f.total,")"]}),e.jsxs(P,{size:"1",variant:h==="active"?"solid":"soft",color:"green",onClick:()=>j("active"),style:{cursor:"pointer",fontWeight:600},children:["🟢 Active (",f.active,")"]}),e.jsxs(P,{size:"1",variant:h==="completed"?"solid":"soft",color:"blue",onClick:()=>j("completed"),style:{cursor:"pointer",fontWeight:600},children:["✅ Done (",f.completed,")"]})]})]}),e.jsxs(a,{align:"center",gap:"2",p:"2",style:{background:"var(--color-surface)",backdropFilter:"blur(16px)",WebkitBackdropFilter:"blur(16px)",borderRadius:"var(--radius-4)",border:"1px solid var(--gray-a5)",boxShadow:"var(--shadow-4, 0 8px 30px rgba(0, 0, 0, 0.12))"},children:[e.jsxs(je,{children:[e.jsx(we,{children:e.jsxs(P,{size:"1",variant:"soft",color:"gray",style:{cursor:"pointer",fontWeight:600},children:[e.jsx(ce,{}),((x=oe[F])==null?void 0:x.name)||"Basemap"]})}),e.jsxs(ke,{variant:"solid",size:"1",children:[e.jsx(Ce,{children:"Select Map Tile"}),Object.values(oe).map(g=>e.jsxs(ee,{onClick:()=>d(g.id),style:{cursor:"pointer",display:"flex",alignItems:"center",justifyContent:"between"},children:[e.jsx("span",{children:g.name}),F===g.id&&e.jsx(te,{style:{marginLeft:8}})]},g.id))]})]}),e.jsxs(je,{children:[e.jsx(we,{children:e.jsxs(P,{size:"1",variant:"soft",color:"gray",style:{cursor:"pointer",fontWeight:600},children:[e.jsx(Ke,{}),"Layers"]})}),e.jsxs(ke,{variant:"solid",size:"1",children:[e.jsx(Ce,{children:"Toggle Overlays"}),e.jsx(ee,{onClick:()=>b("geofences"),style:{cursor:"pointer"},children:e.jsxs(a,{align:"center",gap:"2",children:[l.geofences?e.jsx(ae,{style:{color:"var(--purple-9)"}}):e.jsx(se,{}),e.jsx("span",{children:"Geofence Zones"})]})}),e.jsx(ee,{onClick:()=>b("waypoints"),style:{cursor:"pointer"},children:e.jsxs(a,{align:"center",gap:"2",children:[l.waypoints?e.jsx(ae,{style:{color:"var(--cyan-9)"}}):e.jsx(se,{}),e.jsx("span",{children:"Route Waypoints"})]})}),e.jsx(ee,{onClick:()=>b("trajectories"),style:{cursor:"pointer"},children:e.jsxs(a,{align:"center",gap:"2",children:[l.trajectories?e.jsx(ae,{style:{color:"var(--blue-9)"}}):e.jsx(se,{}),e.jsx("span",{children:"Patrol Trajectories"})]})})]})]}),e.jsxs(P,{size:"1",variant:"soft",color:"gray",onClick:m,style:{cursor:"pointer"},title:"Fit all markers in view",children:[e.jsx(ge,{}),"Fit All"]}),e.jsx(N,{size:"1",variant:"soft",color:"blue",onClick:o,disabled:v,style:{cursor:"pointer"},title:"Refresh live coordinates",children:e.jsx(de,{className:v?"animate-spin":""})}),e.jsxs(P,{size:"1",variant:n?"solid":"soft",color:n?"blue":"gray",onClick:i,style:{cursor:"pointer",fontWeight:600},children:[e.jsx(Y,{}),"Roster (",f.total,")"]}),e.jsx(N,{size:"1",variant:"soft",color:"gray",onClick:r,style:{cursor:"pointer"},title:u?"Exit Fullscreen":"Enter Fullscreen",children:u?e.jsx(er,{}):e.jsx(pe,{})})]})]})})});Ie.displayName="MapHudControls";L.Control.Fullscreen=L.Control.extend({options:{position:"topleft",title:{false:"View Fullscreen",true:"Exit Fullscreen"}},onAdd:function(t){var s=L.DomUtil.create("div","leaflet-control-fullscreen leaflet-bar leaflet-control");return this.link=L.DomUtil.create("a","leaflet-control-fullscreen-button leaflet-bar-part",s),this.link.href="#",this._map=t,this._map.on("fullscreenchange",this._toggleTitle,this),this._toggleTitle(),L.DomEvent.on(this.link,"click",this._click,this),s},_click:function(t){L.DomEvent.stopPropagation(t),L.DomEvent.preventDefault(t),this._map.toggleFullscreen(this.options)},_toggleTitle:function(){this.link.title=this.options.title[this._map.isFullscreen()]}});L.Map.include({isFullscreen:function(){return this._isFullscreen||!1},toggleFullscreen:function(t){var s=this.getContainer();this.isFullscreen()?t&&t.pseudoFullscreen?this._disablePseudoFullscreen(s):document.exitFullscreen?document.exitFullscreen():document.mozCancelFullScreen?document.mozCancelFullScreen():document.webkitCancelFullScreen?document.webkitCancelFullScreen():document.msExitFullscreen?document.msExitFullscreen():this._disablePseudoFullscreen(s):t&&t.pseudoFullscreen?this._enablePseudoFullscreen(s):s.requestFullscreen?s.requestFullscreen():s.mozRequestFullScreen?s.mozRequestFullScreen():s.webkitRequestFullscreen?s.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT):s.msRequestFullscreen?s.msRequestFullscreen():this._enablePseudoFullscreen(s)},_enablePseudoFullscreen:function(t){L.DomUtil.addClass(t,"leaflet-pseudo-fullscreen"),this._setFullscreen(!0),this.fire("fullscreenchange")},_disablePseudoFullscreen:function(t){L.DomUtil.removeClass(t,"leaflet-pseudo-fullscreen"),this._setFullscreen(!1),this.fire("fullscreenchange")},_setFullscreen:function(t){this._isFullscreen=t;var s=this.getContainer();t?L.DomUtil.addClass(s,"leaflet-fullscreen-on"):L.DomUtil.removeClass(s,"leaflet-fullscreen-on"),this.invalidateSize()},_onFullscreenChange:function(t){var s=document.fullscreenElement||document.mozFullScreenElement||document.webkitFullscreenElement||document.msFullscreenElement;s===this.getContainer()&&!this._isFullscreen?(this._setFullscreen(!0),this.fire("fullscreenchange")):s!==this.getContainer()&&this._isFullscreen&&(this._setFullscreen(!1),this.fire("fullscreenchange"))}});L.Map.mergeOptions({fullscreenControl:!1});L.Map.addInitHook(function(){this.options.fullscreenControl&&(this.fullscreenControl=new L.Control.Fullscreen(this.options.fullscreenControl),this.addControl(this.fullscreenControl));var t;if("onfullscreenchange"in document?t="fullscreenchange":"onmozfullscreenchange"in document?t="mozfullscreenchange":"onwebkitfullscreenchange"in document?t="webkitfullscreenchange":"onmsfullscreenchange"in document&&(t="MSFullscreenChange"),t){var s=L.bind(this._onFullscreenChange,this);this.whenReady(function(){L.DomEvent.on(document,t,s)}),this.on("unload",function(){L.DomEvent.off(document,t,s)})}});L.control.fullscreen=function(t){return new L.Control.Fullscreen(t)};const Ee=U.memo(({fitBoundsTrigger:t,users:s,flyToCoords:h,attendanceTypeConfigs:j})=>{const f=fe();return c.useEffect(()=>{if(!f||t===0)return;const F=M.latLngBounds([]);(s||[]).forEach(d=>{const l=d.punchin_location||d.location,b=d.punchout_location;l&&l.lat&&l.lng&&F.extend([parseFloat(l.lat),parseFloat(l.lng)]),b&&b.lat&&b.lng&&F.extend([parseFloat(b.lat),parseFloat(b.lng)])}),(j||[]).forEach(d=>{var l,b;(l=d.config)!=null&&l.polygon&&d.config.polygon.forEach(m=>{m.lat&&m.lng&&F.extend([parseFloat(m.lat),parseFloat(m.lng)])}),(b=d.config)!=null&&b.waypoints&&d.config.waypoints.forEach(m=>{m.lat&&m.lng&&F.extend([parseFloat(m.lat),parseFloat(m.lng)])})}),F.isValid()&&f.fitBounds(F,{padding:[60,60],maxZoom:15,animate:!0,duration:.8})},[f,t,s,j]),c.useEffect(()=>{!f||!h||f.flyTo(h,16,{animate:!0,duration:1.2})},[f,h]),null});Ee.displayName="MapController";const Re=U.memo(({currentTileId:t="voyager",users:s=[],attendanceTypeConfigs:h=[],fitBoundsTrigger:j=0,flyToCoords:f=null,children:F})=>{const d=oe[t]||oe.voyager;return c.useEffect(()=>{const l="team-map-injected-styles";if(!document.getElementById(l)){const b=document.createElement("style");b.id=l,b.innerHTML=dr,document.head.appendChild(b)}},[]),e.jsx("div",{style:{position:"relative",width:"100%",height:"100%"},children:e.jsxs(nr,{center:ar,zoom:sr,minZoom:lr,maxZoom:cr,style:{width:"100%",height:"100%",background:"#0f172a"},scrollWheelZoom:!0,doubleClickZoom:!0,dragging:!0,touchZoom:!0,zoomControl:!1,attributionControl:!1,children:[e.jsx(ir,{url:d.url,subdomains:d.subdomains,maxZoom:d.maxZoom,attribution:d.attribution},d.id),e.jsx(Ee,{fitBoundsTrigger:j,users:s,flyToCoords:f,attendanceTypeConfigs:h}),F]})})});Re.displayName="MapContainerView";const Te=U.memo(({attendanceTypeConfigs:t=[],users:s=[],layerVisibility:h={geofences:!0,waypoints:!0,trajectories:!0}})=>{const j=fe(),f=c.useRef([]);return c.useEffect(()=>{if(!j||(f.current.forEach(d=>{try{j.removeLayer(d)}catch{}}),f.current=[],!t||t.length===0))return;const F=["#3b82f6","#10b981","#f59e0b","#8b5cf6","#ec4899","#06b6d4"];return t.forEach((d,l)=>{const{base_slug:b,config:m,name:o}=d,v=F[l%F.length];if(b==="geo_polygon"&&m&&h.geofences){const n=m.polygon||[],i=m.polygons||[],u=(r,x)=>{const g=r.filter(p=>p&&p.lat&&p.lng);if(g.length<3)return;const z=g.map(p=>[parseFloat(p.lat),parseFloat(p.lng)]),E=M.polygon(z,{color:v,fillColor:v,fillOpacity:.16,weight:2.5,opacity:.85,dashArray:"6, 6"}).addTo(j),k=E.getBounds(),I=k.getCenter(),_=s.filter(p=>{const R=p.punchin_location||p.punchout_location||p.location;if(!R||!R.lat||!R.lng)return!1;const O=M.latLng(parseFloat(R.lat),parseFloat(R.lng));return k.contains(O)}).length,S=`
                        <div class="geofence-centroid-badge" style="border-color: ${v}88;">
                            <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${v};"></span>
                            <span>${x||o}</span>
                            ${_>0?`<span style="background:${v}; color:white; border-radius:10px; padding:0 6px; font-size:10px;">${_} Officers</span>`:""}
                        </div>
                    `,$=M.marker(I,{icon:M.divIcon({html:S,className:"geofence-label-marker",iconSize:[120,26],iconAnchor:[60,13]}),interactive:!1}).addTo(j);E.bindPopup(`
                        <div style="font-family: inherit; padding: 6px; min-width: 140px; color: var(--gray-12, #1e293b);">
                            <div style="font-weight: 700; color: ${v}; font-size: 13px; margin-bottom: 2px;">
                                🛡️ ${x||o}
                            </div>
                            <div style="font-size: 11px; color: var(--gray-10, #64748b);">Geofence Zone Perimeter</div>
                            <div style="font-size: 11px; margin-top: 4px; font-weight: 600;">
                                Verified Officers: <span style="color:${v};">${_}</span>
                            </div>
                        </div>
                    `),f.current.push(E),f.current.push($)};n.length>=3&&u(n,o),i.forEach((r,x)=>{r.points&&r.points.length>=3&&u(r.points,r.name||`${o} Zone ${x+1}`)})}if(b==="route_waypoint"&&m&&h.waypoints){const n=m.waypoints||[],i=m.routes||[],u=(r,x)=>{const g=(r||[]).filter(k=>k&&k.lat&&k.lng);if(g.length<2)return;const z=g.map(k=>[parseFloat(k.lat),parseFloat(k.lng)]),E=M.polyline(z,{color:v,weight:3.5,opacity:.75,dashArray:"8, 6"}).addTo(j);f.current.push(E),g.forEach((k,I)=>{const _=I===0,S=I===g.length-1,p=`
                            <div style="
                                width: 26px;
                                height: 26px;
                                border-radius: 50%;
                                background: ${_?"#10b981":S?"#ef4444":v};
                                border: 2px solid #ffffff;
                                box-shadow: 0 3px 8px rgba(0,0,0,0.35);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                color: white;
                                font-weight: 800;
                                font-size: 11px;
                            ">
                                ${_?"S":S?"E":I+1}
                            </div>
                        `,R=M.marker([parseFloat(k.lat),parseFloat(k.lng)],{icon:M.divIcon({html:p,className:"waypoint-marker",iconSize:[26,26],iconAnchor:[13,13]})}).addTo(j);R.bindPopup(`
                            <div style="font-family: inherit; padding: 4px; color: var(--gray-12, #1e293b);">
                                <strong style="color: ${v};">${x||o}</strong><br>
                                <span style="font-size: 11px; color: var(--gray-10, #64748b);">
                                    ${_?"🚀 Route Start Point":S?"🏁 Route End Point":`Waypoint #${I+1}`}
                                </span>
                            </div>
                        `),f.current.push(R)})};n.length>=2&&u(n,o),i.forEach((r,x)=>{r.waypoints&&r.waypoints.length>=2&&u(r.waypoints,r.name||`${o} Route ${x+1}`)})}}),()=>{f.current.forEach(d=>{try{j.removeLayer(d)}catch{}}),f.current=[]}},[j,t,s,h]),null});Te.displayName="MapGeofenceLayers";const $e=U.memo(({users:t=[],selectedUserId:s,onSelectOfficer:h,onOpenTelemetry:j,onOpenPhoto:f,layerVisibility:F={trajectories:!0}})=>{const d=fe(),l=c.useRef([]),b=c.useRef([]),m=c.useCallback(n=>{if(!n)return null;if(typeof n=="object"&&n.lat&&n.lng){const i=parseFloat(n.lat),u=parseFloat(n.lng);if(!isNaN(i)&&!isNaN(u))return{lat:i,lng:u}}if(typeof n=="string")try{const i=JSON.parse(n);if(i.lat&&i.lng){const u=parseFloat(i.lat),r=parseFloat(i.lng);if(!isNaN(u)&&!isNaN(r))return{lat:u,lng:r}}}catch{const u=n.split(",");if(u.length>=2){const r=parseFloat(u[0].trim()),x=parseFloat(u[1].trim());if(!isNaN(r)&&!isNaN(x))return{lat:r,lng:x}}}return null},[]),o=c.useCallback((n,i="active",u=!1)=>{var $,p;const r=n.status==="active"||i==="punchin",x=i==="punchout",g=n.profile_image_url,z=((p=($=n.name)==null?void 0:$.charAt(0))==null?void 0:p.toUpperCase())||"?",E=r?'<div class="living-marker-radar-ring"></div>':"",k=`living-marker-core ${r?"is-active":x?"is-punchout":"is-completed"}`,I=r?A.active:x?A.punchout:A.completed,_=r?"▶":x?"◼":"✓",S=`
            <div class="living-marker-wrapper" style="${u?"transform: scale(1.25); z-index: 9999;":""}">
                ${E}
                <div class="${k}" style="${u?"border-color: #38bdf8; box-shadow: 0 0 16px #38bdf8;":""}">
                    ${g?`<img src="${g}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; this.parentElement.innerText='${z}';" />`:z}
                </div>
                <div class="living-marker-badge" style="background: ${I};">
                    ${_}
                </div>
            </div>
        `;return M.divIcon({html:S,className:"custom-living-marker",iconSize:[44,44],iconAnchor:[22,22],popupAnchor:[0,-22]})},[]),v=c.useCallback((n,i,u="current")=>{var _,S;const r=n.status==="active",x=(i==null?void 0:i.punchin_time)||n.punchin_time||"--",g=(i==null?void 0:i.punchout_time)||n.punchout_time,z=(i==null?void 0:i.punchin_photo_url)||n.punchin_photo_url,E=(i==null?void 0:i.punchout_photo_url)||n.punchout_photo_url,k=u==="punchout"&&E||z,I=k?`
            <div style="margin: 8px 0; border-radius: 6px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15); max-height: 90px; cursor: pointer; position: relative;"
                 onclick="window.__openMapPhoto && window.__openMapPhoto('${k}', '${n.name.replace(/'/g,"\\'")}', '${x}', '${u}')">
                <img src="${k}" style="width: 100%; height: 85px; object-fit: cover;" alt="Selfie" />
                <div style="position: absolute; bottom: 2px; right: 4px; background: rgba(0,0,0,0.65); padding: 1px 6px; border-radius: 4px; font-size: 9px; color: #fff;">
                    🔍 Zoom
                </div>
            </div>
        `:"";return`
            <div style="
                min-width: 210px;
                max-width: 250px;
                background: var(--color-panel-solid, var(--color-surface, #ffffff));
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                border: 1px solid var(--gray-a6, #cbd5e1);
                border-radius: 12px;
                padding: 12px;
                color: var(--gray-12, #1e293b);
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.18);
                font-family: inherit;
            ">
                <!-- Header -->
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <div style="
                        width: 32px;
                        height: 32px;
                        border-radius: 50%;
                        overflow: hidden;
                        border: 2px solid ${r?A.active:"var(--gray-8, #94a3b8)"};
                        background: var(--gray-a4, #e2e8f0);
                        color: var(--gray-12, #1e293b);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-weight: bold;
                        font-size: 12px;
                        flex-shrink: 0;
                    ">
                        ${n.profile_image_url?`<img src="${n.profile_image_url}" style="width:100%; height:100%; object-fit:cover;" />`:((S=(_=n.name)==null?void 0:_.charAt(0))==null?void 0:S.toUpperCase())||"?"}
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 700; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--gray-12, #0f172a);">
                            ${n.name}
                        </div>
                        <div style="font-size: 10px; color: var(--gray-10, #64748b); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            ${n.designation||"Officer"}
                        </div>
                    </div>
                    <div style="
                        font-size: 9px;
                        font-weight: 600;
                        padding: 2px 6px;
                        border-radius: 10px;
                        background: ${r?"var(--green-a3, rgba(16, 185, 129, 0.15))":"var(--blue-a3, rgba(59, 130, 246, 0.15))"};
                        color: ${r?"var(--green-11, #059669)":"var(--blue-11, #2563eb)"};
                        border: 1px solid ${r?"var(--green-a5, rgba(16, 185, 129, 0.4))":"var(--blue-a5, rgba(59, 130, 246, 0.4))"};
                    ">
                        ${r?"🟢 ACTIVE":"✅ DONE"}
                    </div>
                </div>

                <!-- Timestamps -->
                <div style="background: var(--gray-a3, rgba(0, 0, 0, 0.04)); border-radius: 6px; padding: 6px; margin-bottom: 6px; font-size: 11px; border: 1px solid var(--gray-a4);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 3px;">
                        <span style="color: var(--gray-10, #64748b);">Check In:</span>
                        <span style="font-weight: 600; color: var(--green-11, #059669);">${x}</span>
                    </div>
                    ${g?`
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="color: var(--gray-10, #64748b);">Check Out:</span>
                        <span style="font-weight: 600; color: var(--red-11, #dc2626);">${g}</span>
                    </div>`:""}
                </div>

                ${I}

                <!-- Inspect Button -->
                <button
                    onclick="window.__inspectOfficer && window.__inspectOfficer(${n.user_id})"
                    style="
                        width: 100%;
                        background: linear-gradient(135deg, #0284c7, #2563eb);
                        border: none;
                        border-radius: 6px;
                        color: #ffffff;
                        padding: 6px 10px;
                        font-size: 11px;
                        font-weight: 600;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 4px;
                        margin-top: 6px;
                        box-shadow: 0 2px 6px rgba(37, 99, 235, 0.4);
                    "
                >
                    🔍 Inspect Telemetry
                </button>
            </div>
        `},[]);return c.useEffect(()=>(window.__inspectOfficer=n=>{const i=t.find(u=>u.user_id===n);i&&j&&j(i)},window.__openMapPhoto=(n,i,u,r)=>{f&&f({url:n,officerName:i,timestamp:u,type:r})},()=>{delete window.__inspectOfficer,delete window.__openMapPhoto}),[t,j,f]),c.useEffect(()=>{if(!d||(l.current.forEach(r=>{try{d.removeLayer(r)}catch{}}),l.current=[],b.current.forEach(r=>{try{d.removeLayer(r)}catch{}}),b.current=[],!t||t.length===0))return;const n=[],i=15e-5,u=r=>{let x=r.lat,g=r.lng;const z=n.filter(E=>Math.abs(E.lat-x)<i&&Math.abs(E.lng-g)<i).length;if(z>0){const E=z*1.25,k=18e-5*Math.sqrt(z);x+=Math.cos(E)*k,g+=Math.sin(E)*k}return n.push({lat:x,lng:g}),{lat:x,lng:g}};return t.forEach(r=>{const x=r.cycles&&r.cycles.length>0?r.cycles:null,g=s===r.user_id;if(x)x.forEach((z,E)=>{const k=m(z.punchin_location),I=m(z.punchout_location);if(k&&I&&z.is_complete){const _=u(k),S=u(I),$=M.marker([_.lat,_.lng],{icon:o(r,"punchin",g),zIndexOffset:g?1e3:100}).addTo(d);$.bindPopup(v(r,z,"punchin")),$.on("click",()=>h&&h(r)),l.current.push($);const p=M.marker([S.lat,S.lng],{icon:o(r,"punchout",g),zIndexOffset:g?1e3:90}).addTo(d);if(p.bindPopup(v(r,z,"punchout")),p.on("click",()=>h&&h(r)),l.current.push(p),F.trajectories){const R=M.polyline([[_.lat,_.lng],[S.lat,S.lng]],{color:"#06b6d4",weight:3.5,opacity:.8,className:"patrol-trajectory-path"}).addTo(d);b.current.push(R)}}else{const _=k||I;if(_){const S=u(_),$=M.marker([S.lat,S.lng],{icon:o(r,r.status,g),zIndexOffset:g?1e3:150}).addTo(d);$.bindPopup(v(r,z,"punchin")),$.on("click",()=>h&&h(r)),l.current.push($)}}});else{const z=m(r.punchin_location||r.location),E=m(r.punchout_location),k=z||E;if(k){const I=u(k),_=M.marker([I.lat,I.lng],{icon:o(r,r.status,g),zIndexOffset:g?1e3:100}).addTo(d);if(_.bindPopup(v(r,r,r.status)),_.on("click",()=>h&&h(r)),l.current.push(_),z&&E&&r.punchout_time&&F.trajectories){const S=u(E),$=M.polyline([[I.lat,I.lng],[S.lat,S.lng]],{color:"#06b6d4",weight:3.5,opacity:.8,className:"patrol-trajectory-path"}).addTo(d);b.current.push($)}}}}),()=>{l.current.forEach(r=>{try{d.removeLayer(r)}catch{}}),l.current=[],b.current.forEach(r=>{try{d.removeLayer(r)}catch{}}),b.current=[]}},[d,t,s,F,o,v,m,h]),null});$e.displayName="MapLivingMarkers";const Me=U.memo(({isOpen:t,onClose:s,users:h=[],selectedUserId:j,onSelectOfficer:f,onOpenTelemetry:F,onOpenPhoto:d})=>{const[l,b]=c.useState(""),m=c.useMemo(()=>{if(!l)return h;const o=l.toLowerCase();return h.filter(v=>{var n,i,u,r;return((n=v.name)==null?void 0:n.toLowerCase().includes(o))||((i=v.employee_id)==null?void 0:i.toLowerCase().includes(o))||((u=v.designation)==null?void 0:u.toLowerCase().includes(o))||((r=v.department)==null?void 0:r.toLowerCase().includes(o))})},[h,l]);return t?e.jsxs(y,{style:{position:"absolute",top:74,right:14,bottom:14,width:320,maxWidth:"calc(100vw - 28px)",background:"var(--color-panel-solid, var(--color-surface, #ffffff))",backdropFilter:"blur(20px)",WebkitBackdropFilter:"blur(20px)",borderRadius:"var(--radius-4)",border:"1px solid var(--gray-a5)",boxShadow:"var(--shadow-5, 0 20px 40px rgba(0, 0, 0, 0.25))",zIndex:1e3,display:"flex",flexDirection:"column",overflow:"hidden",animation:"slideInRight 0.25s cubic-bezier(0.16, 1, 0.3, 1)"},children:[e.jsxs(y,{p:"3",style:{borderBottom:"1px solid var(--gray-a4)",background:"var(--gray-a2)"},children:[e.jsxs(a,{justify:"between",align:"center",mb:"2",children:[e.jsxs(a,{align:"center",gap:"2",children:[e.jsx(Y,{style:{color:"var(--blue-9)",width:16,height:16}}),e.jsx(C,{size:"2",weight:"bold",style:{color:"var(--gray-12)"},children:"On-Duty Team Roster"}),e.jsx(H,{size:"1",color:"blue",variant:"solid",radius:"full",children:h.length})]}),e.jsx(N,{size:"1",variant:"ghost",color:"gray",style:{cursor:"pointer"},onClick:s,children:e.jsx(J,{})})]}),e.jsxs(Fe,{size:"1",variant:"surface",placeholder:"Filter roster...",value:l,onChange:o=>b(o.target.value),children:[e.jsx(re,{children:e.jsx(Se,{style:{color:"var(--gray-9)"}})}),l&&e.jsx(re,{children:e.jsx(N,{size:"1",variant:"ghost",color:"gray",onClick:()=>b(""),children:e.jsx(J,{})})})]})]}),e.jsx(y,{p:"2",style:{flex:1,overflowY:"auto",display:"flex",flexDirection:"column",gap:6},children:m.length===0?e.jsxs(a,{align:"center",justify:"center",direction:"column",gap:"2",p:"4",style:{height:"100%"},children:[e.jsx(Y,{style:{color:"var(--gray-8)",width:28,height:28}}),e.jsx(C,{size:"1",color:"gray",children:"No matching officers found"})]}):m.map(o=>{var r,x;const v=j===o.user_id,n=o.status==="active",i=o.punchin_time||"--",u=o.punchout_time;return o.punchin_photo_url||o.profile_image_url,e.jsxs(y,{p:"2",style:{borderRadius:"var(--radius-3)",background:v?"var(--blue-a3)":"var(--gray-a2)",border:v?"1px solid var(--blue-a7)":"1px solid var(--gray-a4)",transition:"all 0.15s ease",cursor:"pointer"},onClick:()=>f(o),children:[e.jsxs(a,{justify:"between",align:"start",gap:"2",children:[e.jsxs(a,{align:"center",gap:"2",style:{minWidth:0,flex:1},children:[e.jsxs(y,{style:{position:"relative",width:34,height:34,borderRadius:"50%",overflow:"hidden",border:`2px solid ${n?A.active:"var(--gray-7)"}`,background:"var(--gray-a4)",display:"flex",alignItems:"center",justifyContent:"center",color:"white",fontWeight:"bold",fontSize:12,flexShrink:0},children:[o.profile_image_url?e.jsx("img",{src:o.profile_image_url,alt:o.name,style:{width:"100%",height:"100%",objectFit:"cover"}}):((x=(r=o.name)==null?void 0:r.charAt(0))==null?void 0:x.toUpperCase())||"?",e.jsx("span",{style:{position:"absolute",bottom:0,right:0,width:8,height:8,borderRadius:"50%",background:n?A.active:A.completed,border:"1px solid var(--color-surface)"}})]}),e.jsxs(y,{style:{minWidth:0,flex:1},children:[e.jsx(C,{size:"2",weight:"bold",style:{color:"var(--gray-12)",whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis",display:"block"},children:o.name}),e.jsxs(C,{size:"1",color:"gray",style:{whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis",display:"block"},children:[o.designation||"Staff"," ",o.employee_id?`• ${o.employee_id}`:""]})]})]}),e.jsx(N,{size:"1",variant:"soft",color:v?"blue":"gray",onClick:g=>{g.stopPropagation(),f(o)},title:"Fly to marker on map",children:e.jsx(ge,{})})]}),e.jsxs(a,{justify:"between",align:"center",mt:"2",pt:"2",style:{borderTop:"1px solid var(--gray-a4)"},children:[e.jsxs(a,{align:"center",gap:"1",children:[e.jsx(Q,{style:{color:"var(--green-9)",width:12,height:12}}),e.jsxs(C,{size:"1",weight:"medium",style:{color:"var(--green-11)"},children:["In: ",i]}),u&&e.jsxs(C,{size:"1",color:"gray",ml:"1",children:["• Out: ",u]})]}),e.jsxs(a,{align:"center",gap:"1",children:[o.punchin_photo_url&&e.jsx(N,{size:"1",variant:"ghost",color:"blue",onClick:g=>{g.stopPropagation(),d({url:o.punchin_photo_url,title:`Check-In Verification: ${o.name}`,timestamp:i,officerName:o.name,employeeId:o.employee_id,designation:o.designation,location:o.punchin_location})},title:"View Check-In Selfie",children:e.jsx(rr,{})}),e.jsxs(P,{size:"1",variant:"surface",color:"gray",onClick:g=>{g.stopPropagation(),F(o)},style:{cursor:"pointer",height:22,fontSize:10,padding:"0 6px"},children:["Telemetry",e.jsx(tr,{})]})]})]})]},o.user_id)})})]}):null});Me.displayName="MapTeamRosterDrawer";const Pe=U.memo(({officer:t,selectedDate:s,onClose:h,onOpenPhoto:j,onFocusMap:f})=>{var $;const[F,d]=c.useState(null);if(!t)return null;const{name:l,employee_id:b,designation:m,department:o,profile_image_url:v,status:n,cycles:i=[],punchin_time:u,punchout_time:r,punchin_location:x,punchout_location:g,punchin_photo_url:z,punchout_photo_url:E,attendance_type:k}=t,I=n==="active",_=(p,R)=>{p&&(navigator.clipboard.writeText(p),d(R),setTimeout(()=>d(null),2e3))},S=i&&i.length>0?i:[{attendance_id:"default",punchin_time:u,punchout_time:r,punchin_location:x,punchout_location:g,punchin_photo_url:z,punchout_photo_url:E,is_complete:!!r}];return e.jsx(y,{style:{position:"fixed",inset:0,background:"rgba(5, 10, 20, 0.75)",backdropFilter:"blur(8px)",WebkitBackdropFilter:"blur(8px)",zIndex:99990,display:"flex",alignItems:"center",justifyContent:"center",padding:16,animation:"fadeIn 0.2s ease-out"},onClick:h,children:e.jsxs(y,{style:{width:"100%",maxWidth:580,maxHeight:"90vh",background:"var(--color-panel-solid, #1e293b)",borderRadius:"var(--radius-4)",border:"1px solid var(--gray-a6)",boxShadow:"0 25px 60px -15px rgba(0,0,0,0.5)",display:"flex",flexDirection:"column",overflow:"hidden"},onClick:p=>p.stopPropagation(),children:[e.jsx(y,{p:"4",style:{background:"linear-gradient(135deg, var(--gray-a3), var(--gray-a4))",borderBottom:"1px solid var(--gray-a5)"},children:e.jsxs(a,{justify:"between",align:"start",children:[e.jsxs(a,{align:"center",gap:"3",children:[e.jsx(y,{style:{width:52,height:52,borderRadius:"50%",overflow:"hidden",border:`3px solid ${I?A.active:"var(--gray-a7)"}`,background:"var(--gray-a4)",display:"flex",alignItems:"center",justifyContent:"center",color:"white",fontWeight:"bold",fontSize:18,flexShrink:0,boxShadow:I?"0 0 12px rgba(16, 185, 129, 0.4)":"none"},children:v?e.jsx("img",{src:v,alt:l,style:{width:"100%",height:"100%",objectFit:"cover"}}):(($=l==null?void 0:l.charAt(0))==null?void 0:$.toUpperCase())||"?"}),e.jsxs(y,{children:[e.jsxs(a,{align:"center",gap:"2",wrap:"wrap",children:[e.jsx(C,{size:"3",weight:"bold",style:{color:"var(--gray-12)"},children:l||"Officer"}),e.jsx(H,{size:"1",color:I?"green":"blue",variant:"solid",radius:"full",children:I?"🟢 Active On-Duty":"✅ Shift Completed"})]}),e.jsxs(C,{size:"1",color:"gray",children:[m||"Employee"," ",o?`• ${o}`:"",b?` • ID: ${b}`:""]}),k&&e.jsxs(H,{size:"1",color:"purple",variant:"soft",mt:"1",children:["Zone: ",k.name||"Standard"]})]})]}),e.jsx(N,{size:"2",variant:"ghost",color:"gray",onClick:h,style:{cursor:"pointer"},children:e.jsx(J,{})})]})}),e.jsxs(y,{p:"4",style:{overflowY:"auto",flex:1,display:"flex",flexDirection:"column",gap:16},children:[e.jsxs(a,{justify:"between",align:"center",children:[e.jsxs(C,{size:"2",weight:"bold",style:{color:"var(--gray-11)"},children:["Attendance & Patrol Telemetry (",S.length," ",S.length===1?"Cycle":"Cycles",")"]}),e.jsxs(C,{size:"1",color:"gray",children:["Date: ",s||"Today"]})]}),S.map((p,R)=>{const O=p.punchin_location,W=p.punchout_location,B=O&&O.lat&&O.lng?`${parseFloat(O.lat).toFixed(5)}, ${parseFloat(O.lng).toFixed(5)}`:null,V=W&&W.lat&&W.lng?`${parseFloat(W.lat).toFixed(5)}, ${parseFloat(W.lng).toFixed(5)}`:null;return e.jsxs(y,{p:"3",style:{background:"var(--gray-a2)",borderRadius:"var(--radius-3)",border:"1px solid var(--gray-a4)"},children:[e.jsxs(a,{justify:"between",align:"center",mb:"3",children:[e.jsxs(H,{size:"1",color:"gray",variant:"surface",children:["Shift Cycle #",R+1]}),e.jsx(H,{size:"1",color:p.is_complete?"blue":"green",variant:"soft",children:p.is_complete?"Cycle Finished":"Active Cycle"})]}),e.jsxs(a,{direction:"column",gap:"3",children:[e.jsxs(a,{align:"start",justify:"between",p:"2",style:{background:"var(--green-a2)",borderRadius:"var(--radius-2)",border:"1px solid var(--green-a4)"},children:[e.jsxs(a,{align:"start",gap:"2",style:{flex:1},children:[e.jsx(y,{style:{width:24,height:24,borderRadius:"50%",background:A.punchin,color:"white",display:"flex",alignItems:"center",justifyContent:"center",flexShrink:0},children:e.jsx(Q,{style:{width:14,height:14}})}),e.jsxs(y,{children:[e.jsxs(C,{size:"1",weight:"bold",style:{color:"var(--green-11)"},children:["Check-In: ",p.punchin_time||"--"]}),B?e.jsxs(a,{align:"center",gap:"1",mt:"1",children:[e.jsx(he,{style:{color:"var(--green-9)",width:12,height:12}}),e.jsx(C,{size:"1",style:{fontSize:11,fontFamily:"monospace",color:"var(--gray-11)"},children:B}),e.jsx(N,{size:"1",variant:"ghost",style:{height:18,width:18},onClick:()=>_(B,`in-${R}`),children:F===`in-${R}`?e.jsx(te,{}):e.jsx(ue,{})})]}):e.jsx(C,{size:"1",color:"gray",style:{fontSize:11},children:"No GPS coordinates"})]})]}),p.punchin_photo_url&&e.jsxs(y,{style:{width:48,height:48,borderRadius:"var(--radius-2)",overflow:"hidden",border:"1px solid var(--green-a6)",cursor:"pointer",position:"relative",flexShrink:0},onClick:()=>j&&j({url:p.punchin_photo_url,officerName:l,designation:m,timestamp:p.punchin_time,location:O,type:"punchin"}),children:[e.jsx("img",{src:p.punchin_photo_url,alt:"Check-in selfie",style:{width:"100%",height:"100%",objectFit:"cover"}}),e.jsx(y,{style:{position:"absolute",bottom:0,insetInline:0,background:"rgba(0,0,0,0.6)",display:"flex",alignItems:"center",justifyContent:"center",padding:1},children:e.jsx(pe,{style:{color:"white",width:10,height:10}})})]})]}),p.punchout_time?e.jsxs(a,{align:"start",justify:"between",p:"2",style:{background:"var(--red-a2)",borderRadius:"var(--radius-2)",border:"1px solid var(--red-a4)"},children:[e.jsxs(a,{align:"start",gap:"2",style:{flex:1},children:[e.jsx(y,{style:{width:24,height:24,borderRadius:"50%",background:A.punchout,color:"white",display:"flex",alignItems:"center",justifyContent:"center",flexShrink:0},children:e.jsx(ze,{style:{width:14,height:14}})}),e.jsxs(y,{children:[e.jsxs(C,{size:"1",weight:"bold",style:{color:"var(--red-11)"},children:["Check-Out: ",p.punchout_time]}),V?e.jsxs(a,{align:"center",gap:"1",mt:"1",children:[e.jsx(he,{style:{color:"var(--red-9)",width:12,height:12}}),e.jsx(C,{size:"1",style:{fontSize:11,fontFamily:"monospace",color:"var(--gray-11)"},children:V}),e.jsx(N,{size:"1",variant:"ghost",style:{height:18,width:18},onClick:()=>_(V,`out-${R}`),children:F===`out-${R}`?e.jsx(te,{}):e.jsx(ue,{})})]}):e.jsx(C,{size:"1",color:"gray",style:{fontSize:11},children:"No GPS coordinates"})]})]}),p.punchout_photo_url&&e.jsxs(y,{style:{width:48,height:48,borderRadius:"var(--radius-2)",overflow:"hidden",border:"1px solid var(--red-a6)",cursor:"pointer",position:"relative",flexShrink:0},onClick:()=>j&&j({url:p.punchout_photo_url,officerName:l,designation:m,timestamp:p.punchout_time,location:W,type:"punchout"}),children:[e.jsx("img",{src:p.punchout_photo_url,alt:"Check-out selfie",style:{width:"100%",height:"100%",objectFit:"cover"}}),e.jsx(y,{style:{position:"absolute",bottom:0,insetInline:0,background:"rgba(0,0,0,0.6)",display:"flex",alignItems:"center",justifyContent:"center",padding:1},children:e.jsx(pe,{style:{color:"white",width:10,height:10}})})]})]}):e.jsxs(a,{align:"center",gap:"2",p:"2",style:{background:"var(--gray-a3)",borderRadius:"var(--radius-2)",border:"1px dashed var(--gray-a5)"},children:[e.jsx(Q,{style:{color:"var(--amber-9)"}}),e.jsx(C,{size:"1",color:"gray",children:"Officer is currently on active patrol. Check-out not recorded yet."})]})]})]},R)})]}),e.jsx(y,{p:"3",style:{background:"var(--gray-a2)",borderTop:"1px solid var(--gray-a4)"},children:e.jsxs(a,{justify:"between",align:"center",gap:"2",children:[e.jsxs(P,{variant:"surface",color:"blue",size:"2",onClick:()=>{if(h(),f){const p=x||g;p&&p.lat&&p.lng&&f([parseFloat(p.lat),parseFloat(p.lng)])}},children:[e.jsx(ge,{})," Focus on Map"]}),e.jsx(P,{variant:"outline",color:"gray",size:"2",onClick:h,children:"Close"})]})})]})})});Pe.displayName="OfficerDetailModal";const Oe=U.memo(({photoData:t,onClose:s})=>{const[h,j]=U.useState(!1);if(!t||!t.url)return null;const{url:f,title:F,officerName:d,designation:l,timestamp:b,location:m,type:o}=t,v=m&&m.lat&&m.lng?`${parseFloat(m.lat).toFixed(6)}, ${parseFloat(m.lng).toFixed(6)}`:null,n=()=>{v&&(navigator.clipboard.writeText(v),j(!0),setTimeout(()=>j(!1),2e3))};return e.jsxs(y,{style:{position:"fixed",inset:0,background:"rgba(0, 0, 0, 0.85)",backdropFilter:"blur(16px)",WebkitBackdropFilter:"blur(16px)",zIndex:99999,display:"flex",alignItems:"center",justifyContent:"center",padding:24,animation:"fadeIn 0.2s ease-out"},onClick:s,children:[e.jsx(N,{size:"3",variant:"solid",color:"gray",highContrast:!0,style:{position:"absolute",top:24,right:24,borderRadius:"50%",cursor:"pointer",zIndex:10},onClick:i=>{i.stopPropagation(),s()},"aria-label":"Close photo preview",children:e.jsx(J,{style:{width:22,height:22}})}),e.jsxs(y,{style:{maxWidth:"90vw",maxHeight:"90vh",display:"flex",flexDirection:"column",alignItems:"center",background:"var(--color-panel-solid, var(--color-surface, #ffffff))",border:"1px solid var(--gray-a5)",borderRadius:"var(--radius-4)",boxShadow:"var(--shadow-6, 0 25px 60px -15px rgba(0, 0, 0, 0.5))",overflow:"hidden"},onClick:i=>i.stopPropagation(),children:[e.jsx(y,{p:"3",style:{width:"100%",borderBottom:"1px solid var(--gray-a4)",background:"var(--gray-a2)"},children:e.jsxs(a,{justify:"between",align:"center",gap:"3",px:"2",children:[e.jsxs(a,{align:"center",gap:"2",children:[e.jsx(Y,{style:{color:"var(--blue-9)",width:18,height:18}}),e.jsxs(y,{children:[e.jsx(C,{size:"2",weight:"bold",style:{color:"var(--gray-12)"},children:d||"Officer Photo"}),l&&e.jsx(C,{size:"1",color:"gray",style:{display:"block"},children:l})]})]}),e.jsx(H,{size:"1",color:o==="punchin"?"green":o==="punchout"?"red":"blue",variant:"solid",children:o==="punchin"?"Check-In Photo":o==="punchout"?"Check-Out Photo":F||"Verification Selfie"})]})}),e.jsx(y,{style:{display:"flex",alignItems:"center",justifyContent:"center",padding:16,maxHeight:"65vh",minWidth:320,maxWidth:720,overflow:"hidden"},children:e.jsx("img",{src:f,alt:"Officer Telemetry Verification",style:{maxWidth:"100%",maxHeight:"60vh",objectFit:"contain",borderRadius:"var(--radius-3)",border:"1px solid var(--gray-a4)",boxShadow:"var(--shadow-4)"}})}),e.jsx(y,{p:"3",style:{width:"100%",borderTop:"1px solid var(--gray-a4)",background:"var(--gray-a2)"},children:e.jsxs(a,{justify:"between",align:"center",gap:"3",wrap:"wrap",px:"2",children:[e.jsxs(a,{align:"center",gap:"4",wrap:"wrap",children:[b&&e.jsxs(a,{align:"center",gap:"1",children:[e.jsx(Q,{style:{color:"var(--purple-9)",width:14,height:14}}),e.jsx(C,{size:"1",style:{color:"var(--gray-12)"},children:b})]}),v&&e.jsxs(a,{align:"center",gap:"2",children:[e.jsx(he,{style:{color:"var(--green-9)",width:14,height:14}}),e.jsx(C,{size:"1",style:{color:"var(--gray-12)",fontFamily:"monospace"},children:v}),e.jsxs(P,{size:"1",variant:"ghost",color:"gray",style:{cursor:"pointer",padding:"0 4px",height:20},onClick:n,children:[h?e.jsx(te,{style:{color:"var(--green-9)"}}):e.jsx(ue,{}),e.jsx("span",{style:{fontSize:10},children:h?"Copied":"Copy"})]})]})]}),e.jsx("a",{href:f,target:"_blank",rel:"noopener noreferrer",download:!0,style:{textDecoration:"none"},children:e.jsxs(P,{size:"1",variant:"soft",color:"blue",style:{cursor:"pointer"},children:[e.jsx(or,{}),"Download HD"]})})]})})]})]})});Oe.displayName="PhotoTelemetryLightbox";const pr=U.memo(({selectedDate:t,updateMap:s})=>{const[h,j]=c.useState([]),[f,F]=c.useState([]),[d,l]=c.useState(!0),[b,m]=c.useState(!1),[o,v]=c.useState(null),[n,i]=c.useState(""),[u,r]=c.useState("all"),[x,g]=c.useState(()=>localStorage.getItem("guardian_map_tile_id")||"voyager"),[z,E]=c.useState({geofences:!0,waypoints:!0,trajectories:!0}),[k,I]=c.useState(!0),[_,S]=c.useState(!1),[$,p]=c.useState(null),[R,O]=c.useState(null),[W,B]=c.useState(null),[V,Ae]=c.useState(0),[Ne,xe]=c.useState(null),[ne,hr]=c.useState(!0),[Ue,me]=c.useState(le),ie=c.useRef(null);c.useRef(null);const We=c.useCallback(w=>{g(w),localStorage.setItem("guardian_map_tile_id",w)},[]),De=c.useCallback(w=>{E(T=>({...T,[w]:!T[w]}))},[]),Z=c.useCallback(async(w=!1)=>{if(t){w?m(!0):l(!0);try{const T=route("getUserLocationsForDate",{date:t.split("T")[0],_t:Date.now()}),D=await fetch(T);if(!D.ok)throw new Error(`HTTP ${D.status}: Failed to fetch user locations`);const G=await D.json(),K=Array.isArray(G.locations)?G.locations:[],q=Array.isArray(G.attendance_type_configs)?G.attendance_type_configs:[];j(K),F(q),v(new Date),me(le)}catch(T){console.error("Failed to load team locations:",T)}finally{l(!1),m(!1)}}},[t]);c.useEffect(()=>{Z(!1)},[t,s,Z]),c.useEffect(()=>{if(!ne)return;const w=setInterval(()=>{me(T=>T<=1?(Z(!0),le):T-1)},1e3);return()=>clearInterval(w)},[ne,Z]);const ye=c.useMemo(()=>{const w=h.length;let T=0,D=0;return h.forEach(G=>{G.status==="active"?T++:D++}),{total:w,checkedIn:T,active:T,completed:D}},[h]),X=c.useMemo(()=>h.filter(w=>{var T,D,G,K;if(u==="active"&&w.status!=="active"||u==="completed"&&w.status==="active")return!1;if(n){const q=n.toLowerCase(),qe=(T=w.name)==null?void 0:T.toLowerCase().includes(q),Ve=(D=w.employee_id)==null?void 0:D.toLowerCase().includes(q),Ye=(G=w.designation)==null?void 0:G.toLowerCase().includes(q),Qe=(K=w.department)==null?void 0:K.toLowerCase().includes(q);if(!qe&&!Ve&&!Ye&&!Qe)return!1}return!0}),[h,u,n]),Ge=c.useMemo(()=>o?o.toLocaleTimeString("en-US",{hour:"2-digit",minute:"2-digit",second:"2-digit",hour12:!0}):null,[o]),be=c.useMemo(()=>{if(!t)return"Invalid Date";try{return new Date(t).toLocaleString("en-US",{weekday:"long",year:"numeric",month:"long",day:"numeric"})}catch{return t}},[t]),ve=c.useCallback(w=>{p(w.user_id);const T=w.punchin_location||w.punchout_location||w.location;T&&T.lat&&T.lng&&xe([parseFloat(T.lat),parseFloat(T.lng)])},[]),Be=c.useCallback(w=>{xe(w)},[]),Ze=c.useCallback(()=>{Ae(w=>w+1)},[]),He=c.useCallback(()=>{ie.current&&(document.fullscreenElement?(document.exitFullscreen(),S(!1)):(ie.current.requestFullscreen().catch(w=>{console.warn("Fullscreen error:",w)}),S(!0)))},[]);return c.useEffect(()=>{const w=()=>{S(!!document.fullscreenElement)};return document.addEventListener("fullscreenchange",w),()=>document.removeEventListener("fullscreenchange",w)},[]),e.jsxs(y,{children:[e.jsxs(Xe,{mb:"4",children:[e.jsx(y,{p:"4",style:{borderBottom:"1px solid var(--gray-a4)"},children:e.jsxs(a,{justify:"between",align:"center",gap:"3",wrap:"wrap",children:[e.jsxs(a,{align:"center",gap:"3",children:[e.jsx(y,{style:{padding:10,borderRadius:"var(--radius-3)",background:"linear-gradient(135deg, var(--blue-a3), var(--blue-a4))",border:"1px solid var(--blue-a6)",width:44,height:44,flexShrink:0,display:"flex",alignItems:"center",justifyContent:"center",boxShadow:"0 2px 8px rgba(0,0,0,0.06)"},children:e.jsx(ce,{style:{color:"var(--blue-9)",width:22,height:22}})}),e.jsxs(y,{children:[e.jsx(_e,{size:"4",style:{letterSpacing:"-0.02em"},children:"Team Locations & Live GIS Command Center"}),e.jsx(C,{size:"2",color:"gray",children:be})]})]}),e.jsxs(P,{variant:"surface",size:"1",color:"blue",onClick:()=>Z(!1),disabled:d||b,style:{cursor:"pointer"},children:[e.jsx(de,{className:d||b?"animate-spin":""}),"Refresh Live Feed"]})]})}),e.jsx(Le,{stats:ye,lastUpdateText:Ge,isPolling:ne,secondsLeft:Ue}),e.jsx(y,{p:"4",children:d?e.jsx(a,{align:"center",justify:"center",style:{height:"72vh",border:"1px solid var(--gray-a4)",borderRadius:"var(--radius-3)",background:"var(--gray-a2)"},children:e.jsxs(a,{direction:"column",align:"center",gap:"3",children:[e.jsx(Je,{size:"3"}),e.jsx(C,{size:"2",weight:"medium",color:"gray",children:"Loading team coordinates & GIS boundaries..."})]})}):h.length===0?e.jsxs(a,{direction:"column",align:"center",justify:"center",gap:"3",p:"6",style:{height:"72vh",border:"1px solid var(--gray-a4)",borderRadius:"var(--radius-3)",background:"var(--gray-a2)"},children:[e.jsx(ce,{style:{width:64,height:64,color:"var(--gray-7)"}}),e.jsx(_e,{size:"4",children:"No Team Location Records Found"}),e.jsxs(C,{size:"2",color:"gray",align:"center",style:{maxWidth:420},children:["No check-in or patrol coordinates recorded for ",be,". Ensure team members have logged attendance via mobile GPS or check a different date."]}),e.jsxs(P,{variant:"outline",onClick:()=>Z(!1),children:[e.jsx(de,{})," Refresh Data"]})]}):e.jsxs(y,{ref:ie,style:{position:"relative",height:_?"100vh":"72vh",borderRadius:_?0:"var(--radius-3)",overflow:"hidden",border:_?"none":"1px solid var(--gray-a5)",boxShadow:"0 8px 30px rgba(0,0,0,0.12)"},children:[e.jsx(Ie,{searchQuery:n,onSearchChange:i,statusFilter:u,onStatusFilterChange:r,stats:ye,currentTileId:x,onTileChange:We,layerVisibility:z,onToggleLayer:De,onFitBounds:Ze,onRefresh:()=>Z(!0),isRefreshing:b,isDrawerOpen:k,onToggleDrawer:()=>I(w=>!w),isFullscreen:_,onToggleFullscreen:He}),e.jsxs(Re,{currentTileId:x,users:X,attendanceTypeConfigs:f,fitBoundsTrigger:V,flyToCoords:Ne,children:[e.jsx(Te,{attendanceTypeConfigs:f,users:X,layerVisibility:z}),e.jsx($e,{users:X,selectedUserId:$,onSelectOfficer:ve,onOpenTelemetry:O,onOpenPhoto:B,layerVisibility:z})]}),e.jsx(Me,{isOpen:k,onClose:()=>I(!1),users:X,selectedUserId:$,onSelectOfficer:ve,onOpenTelemetry:O,onOpenPhoto:B})]})})]}),R&&e.jsx(Pe,{officer:R,selectedDate:t,onClose:()=>O(null),onOpenPhoto:B,onFocusMap:Be}),W&&e.jsx(Oe,{photoData:W,onClose:()=>B(null)})]})});pr.displayName="UserLocationsCard";export{pr as U};
