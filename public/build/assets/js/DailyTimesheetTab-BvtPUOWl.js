import{j as e,a as m,p as n,b as f,e as Z,u as ze,c as te,h as N,d as O,_ as ve,$ as we,a0 as ke,al as Ce,a1 as ee,t as _e,s as Je}from"./vendor-radix-DY8pb31i.js";import{R as A,a as u}from"./vendor-inertia-BheeDqvO.js";import"./logRange-CUdWC1SU.js";import"./useObjectionsListState-FGnx5z52.js";import{P as Xe}from"./ObjectionsStatsSection-CLP5dmif.js";import{m as V,c as Q,d as ge,M as Fe,g as J,G as ce,_ as re,L as Ke,s as se,ab as ae,av as Se,R as de,aw as et,ax as pe,b as tt,af as ue,$ as he,p as rt}from"./react-icons.esm-C7QcFAin.js";import{L as P}from"./leaflet-GjjsV4zE.js";import{b as fe,M as ot,T as nt}from"./TileLayer-gT5JnGbW.js";import"./DepartmentForm-N7AyyY4k.js";import"./ErrorBoundary-9bBkpxZ4.js";import"./MonthlyCalendarTab-DnnRbtUa.js";import"./index.esm-MmCp14hd.js";import"./firebase-config-AUwd4BOu.js";import"./vendor-utils-Bd_1ICpc.js";const oe={voyager:{id:"voyager",name:"Voyager (Crisp)",icon:"Compass",url:"https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png",subdomains:"abcd",maxZoom:20,attribution:'&copy; <a href="https://carto.com/">CARTO</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'},darkMatter:{id:"darkMatter",name:"Dark Matter (Midnight)",icon:"Moon",url:"https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png",subdomains:"abcd",maxZoom:20,attribution:'&copy; <a href="https://carto.com/">CARTO</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'},positron:{id:"positron",name:"Positron (Minimal)",icon:"Sun",url:"https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png",subdomains:"abcd",maxZoom:20,attribution:'&copy; <a href="https://carto.com/">CARTO</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'},satellite:{id:"satellite",name:"Satellite (Aerial HD)",icon:"Globe",url:"https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",subdomains:"",maxZoom:19,attribution:"Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community"},osm:{id:"osm",name:"OpenStreetMap",icon:"Map",url:"https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",subdomains:"abc",maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'}},it=[23.8103,90.4125],st=12,at=7,lt=19,le=15,U={active:"#10b981",completed:"#3b82f6",punchin:"#10b981",punchout:"#ef4444"},ct=`
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
    border: 2.5px solid #ffffff;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: linear-gradient(135deg, #1e293b, #0f172a);
    color: #ffffff;
    font-weight: 700;
    font-size: 13px;
    z-index: 2;
}

.living-marker-core.is-active {
    border-color: #10b981;
    animation: beaconGlow 2.5s ease-in-out infinite;
}

.living-marker-core.is-completed {
    border-color: #64748b;
}

.living-marker-core.is-punchin {
    border-color: #10b981;
}

.living-marker-core.is-punchout {
    border-color: #ef4444;
}

.living-marker-badge {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    color: #ffffff;
    z-index: 3;
}

/* Glassmorphism Leaflet Popup */
.leaflet-popup-content-wrapper {
    background: transparent !important;
    box-shadow: none !important;
    padding: 0 !important;
    border-radius: 12px !important;
}

.leaflet-popup-content {
    margin: 0 !important;
    line-height: normal !important;
}

.leaflet-popup-tip {
    background: var(--color-panel-solid, #1e293b) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25) !important;
}

/* Centroid Labels for Polygons */
.geofence-centroid-badge {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 3px 10px;
    color: #ffffff;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Trajectory flowing dashes */
.patrol-trajectory-path {
    stroke-dasharray: 8 6;
    animation: dashFlow 1.2s linear infinite;
}
`,Le=A.memo(({stats:t,lastUpdateText:c,isPolling:d,secondsLeft:w})=>{const s=(t==null?void 0:t.total)||0,C=(t==null?void 0:t.checkedIn)??(t==null?void 0:t.active)??0,h=(t==null?void 0:t.completed)||0,a=s>0?Math.round(C/s*100):0;return e.jsx(m,{p:"3",style:{background:"linear-gradient(135deg, var(--gray-a2), var(--gray-a3))",borderBottom:"1px solid var(--gray-a4)"},children:e.jsxs(n,{justify:"between",align:"center",gap:"3",wrap:"wrap",children:[e.jsxs(n,{align:"center",gap:"3",wrap:"wrap",children:[e.jsxs(n,{align:"center",gap:"2",px:"3",py:"2",style:{borderRadius:"var(--radius-3)",background:"var(--color-panel-solid, #ffffff)",border:"1px solid var(--gray-a4)",boxShadow:"0 1px 3px rgba(0,0,0,0.05)"},children:[e.jsx(n,{align:"center",justify:"center",style:{width:28,height:28,borderRadius:"50%",background:"var(--blue-a3)",color:"var(--blue-9)"},children:e.jsx(V,{style:{width:16,height:16}})}),e.jsxs(m,{children:[e.jsxs(n,{align:"baseline",gap:"1",children:[e.jsx(f,{size:"4",weight:"bold",style:{color:"var(--gray-12)"},children:s}),e.jsx(f,{size:"1",color:"gray",children:"Officers"})]}),e.jsx(f,{size:"1",color:"gray",style:{fontSize:10,display:"block",marginTop:-2},children:"Total Tracked"})]})]}),e.jsxs(n,{align:"center",gap:"2",px:"3",py:"2",style:{borderRadius:"var(--radius-3)",background:"var(--color-panel-solid, #ffffff)",border:"1px solid var(--green-a5)",boxShadow:"0 1px 3px rgba(0,0,0,0.05)"},children:[e.jsxs(n,{align:"center",justify:"center",style:{width:28,height:28,borderRadius:"50%",background:"var(--green-a3)",color:"var(--green-9)",position:"relative"},children:[e.jsx(Q,{style:{width:16,height:16}}),C>0&&e.jsx("span",{style:{position:"absolute",top:1,right:1,width:8,height:8,borderRadius:"50%",background:U.active,border:"1.5px solid white"}})]}),e.jsxs(m,{children:[e.jsxs(n,{align:"baseline",gap:"1",children:[e.jsx(f,{size:"4",weight:"bold",style:{color:"var(--green-11)"},children:C}),e.jsxs(Z,{size:"1",color:"green",variant:"soft",radius:"full",children:[a,"%"]})]}),e.jsx(f,{size:"1",color:"gray",style:{fontSize:10,display:"block",marginTop:-2},children:"Active On-Duty"})]})]}),e.jsxs(n,{align:"center",gap:"2",px:"3",py:"2",style:{borderRadius:"var(--radius-3)",background:"var(--color-panel-solid, #ffffff)",border:"1px solid var(--gray-a4)",boxShadow:"0 1px 3px rgba(0,0,0,0.05)"},children:[e.jsx(n,{align:"center",justify:"center",style:{width:28,height:28,borderRadius:"50%",background:"var(--blue-a3)",color:"var(--blue-9)"},children:e.jsx(ge,{style:{width:16,height:16}})}),e.jsxs(m,{children:[e.jsxs(n,{align:"baseline",gap:"1",children:[e.jsx(f,{size:"4",weight:"bold",style:{color:"var(--blue-11)"},children:h}),e.jsx(f,{size:"1",color:"gray",children:"Completed"})]}),e.jsx(f,{size:"1",color:"gray",style:{fontSize:10,display:"block",marginTop:-2},children:"Finished Shifts"})]})]})]}),e.jsxs(n,{align:"center",gap:"2",children:[e.jsxs(n,{align:"center",gap:"2",px:"2",py:"1",style:{background:"var(--gray-a3)",borderRadius:"var(--radius-2)",border:"1px solid var(--gray-a4)"},children:[e.jsx(n,{align:"center",justify:"center",style:{width:8,height:8,borderRadius:"50%",background:d?U.active:"var(--gray-8)",boxShadow:d?"0 0 8px #10b981":"none"}}),e.jsx(f,{size:"1",color:"gray",children:d?`Live Sync (${w}s)`:"Polling Paused"})]}),c&&e.jsxs(f,{size:"1",color:"gray",style:{fontSize:11},children:["Updated: ",c]})]})]})})});Le.displayName="MapStatsRibbon";const Ee=A.memo(({searchQuery:t,onSearchChange:c,statusFilter:d,onStatusFilterChange:w,stats:s,currentTileId:C,onTileChange:h,layerVisibility:a,onToggleLayer:y,onFitBounds:j,onRefresh:l,isRefreshing:v,isDrawerOpen:i,onToggleDrawer:o,isFullscreen:p,onToggleFullscreen:r})=>{var x;return e.jsx(m,{style:{position:"absolute",top:14,left:14,right:14,zIndex:1e3,pointerEvents:"none"},children:e.jsxs(n,{gap:"2",align:"center",justify:"between",wrap:"wrap",style:{pointerEvents:"auto"},children:[e.jsxs(n,{align:"center",gap:"2",wrap:"wrap",p:"2",style:{background:"rgba(15, 23, 42, 0.85)",backdropFilter:"blur(16px)",WebkitBackdropFilter:"blur(16px)",borderRadius:"var(--radius-4)",border:"1px solid rgba(255, 255, 255, 0.15)",boxShadow:"0 8px 32px 0 rgba(0, 0, 0, 0.37)"},children:[e.jsx(m,{style:{width:200},children:e.jsxs(ze,{size:"1",placeholder:"Search officer or ID...",value:t,onChange:b=>c(b.target.value),style:{background:"rgba(255, 255, 255, 0.1)",color:"#ffffff",border:"1px solid rgba(255, 255, 255, 0.2)",borderRadius:"var(--radius-2)"},children:[e.jsx(te,{children:e.jsx(Fe,{style:{color:"#94a3b8"}})}),t&&e.jsx(te,{children:e.jsx(N,{size:"1",variant:"ghost",style:{color:"#cbd5e1",cursor:"pointer"},onClick:()=>c(""),children:e.jsx(J,{})})})]})}),e.jsxs(n,{align:"center",gap:"1",children:[e.jsxs(O,{size:"1",variant:d==="all"?"solid":"soft",color:d==="all"?"blue":"gray",style:{cursor:"pointer",borderRadius:"var(--radius-2)",fontSize:11},onClick:()=>w("all"),children:["All (",(s==null?void 0:s.total)||0,")"]}),e.jsxs(O,{size:"1",variant:d==="active"?"solid":"soft",color:d==="active"?"green":"gray",style:{cursor:"pointer",borderRadius:"var(--radius-2)",fontSize:11},onClick:()=>w("active"),children:["🟢 Active (",(s==null?void 0:s.checkedIn)??(s==null?void 0:s.active)??0,")"]}),e.jsxs(O,{size:"1",variant:d==="completed"?"solid":"soft",color:d==="completed"?"blue":"gray",style:{cursor:"pointer",borderRadius:"var(--radius-2)",fontSize:11},onClick:()=>w("completed"),children:["✅ Done (",(s==null?void 0:s.completed)||0,")"]})]})]}),e.jsxs(n,{align:"center",gap:"2",p:"2",style:{background:"rgba(15, 23, 42, 0.85)",backdropFilter:"blur(16px)",WebkitBackdropFilter:"blur(16px)",borderRadius:"var(--radius-4)",border:"1px solid rgba(255, 255, 255, 0.15)",boxShadow:"0 8px 32px 0 rgba(0, 0, 0, 0.37)"},children:[e.jsxs(ve,{children:[e.jsx(we,{children:e.jsxs(O,{size:"1",variant:"soft",color:"gray",style:{color:"#ffffff",background:"rgba(255, 255, 255, 0.12)",cursor:"pointer"},children:[e.jsx(ce,{}),e.jsx(f,{size:"1",style:{fontSize:11},children:((x=oe[C])==null?void 0:x.name)||"Base Map"})]})}),e.jsxs(ke,{style:{background:"rgba(15, 23, 42, 0.95)",backdropFilter:"blur(12px)",border:"1px solid rgba(255, 255, 255, 0.2)",color:"#ffffff",zIndex:9999},children:[e.jsx(Ce,{style:{color:"#94a3b8",fontSize:10},children:"MAP TILE THEME"}),Object.values(oe).map(b=>e.jsx(ee,{style:{cursor:"pointer",color:C===b.id?"#38bdf8":"#e2e8f0",fontWeight:C===b.id?700:400},onClick:()=>h(b.id),children:e.jsxs(n,{align:"center",justify:"between",style:{width:"100%"},children:[e.jsx(f,{size:"1",children:b.name}),C===b.id&&e.jsx(re,{style:{width:14,height:14}})]})},b.id))]})]}),e.jsxs(ve,{children:[e.jsx(we,{children:e.jsxs(O,{size:"1",variant:"soft",color:"gray",style:{color:"#ffffff",background:"rgba(255, 255, 255, 0.12)",cursor:"pointer"},children:[e.jsx(Ke,{}),e.jsx(f,{size:"1",style:{fontSize:11},children:"Layers"})]})}),e.jsxs(ke,{style:{background:"rgba(15, 23, 42, 0.95)",backdropFilter:"blur(12px)",border:"1px solid rgba(255, 255, 255, 0.2)",color:"#ffffff",zIndex:9999},children:[e.jsx(Ce,{style:{color:"#94a3b8",fontSize:10},children:"TOGGLE MAP OVERLAYS"}),e.jsx(ee,{style:{cursor:"pointer",color:"#e2e8f0"},onClick:()=>y("geofences"),children:e.jsxs(n,{align:"center",justify:"between",style:{width:"100%"},children:[e.jsx(f,{size:"1",children:"Geofence Zones"}),a.geofences?e.jsx(se,{style:{color:"#34d399"}}):e.jsx(ae,{style:{color:"#94a3b8"}})]})}),e.jsx(ee,{style:{cursor:"pointer",color:"#e2e8f0"},onClick:()=>y("waypoints"),children:e.jsxs(n,{align:"center",justify:"between",style:{width:"100%"},children:[e.jsx(f,{size:"1",children:"Route Waypoints"}),a.waypoints?e.jsx(se,{style:{color:"#34d399"}}):e.jsx(ae,{style:{color:"#94a3b8"}})]})}),e.jsx(ee,{style:{cursor:"pointer",color:"#e2e8f0"},onClick:()=>y("trajectories"),children:e.jsxs(n,{align:"center",justify:"between",style:{width:"100%"},children:[e.jsx(f,{size:"1",children:"Patrol Trajectories"}),a.trajectories?e.jsx(se,{style:{color:"#34d399"}}):e.jsx(ae,{style:{color:"#94a3b8"}})]})})]})]}),e.jsx(N,{size:"1",variant:"soft",color:"gray",style:{color:"#ffffff",background:"rgba(255, 255, 255, 0.12)",cursor:"pointer"},title:"Fit all markers in view",onClick:j,children:e.jsx(Se,{})}),e.jsx(N,{size:"1",variant:"soft",color:"gray",style:{color:"#ffffff",background:"rgba(255, 255, 255, 0.12)",cursor:"pointer"},title:"Refresh live locations",onClick:l,disabled:v,children:e.jsx(de,{className:v?"animate-spin":""})}),e.jsxs(O,{size:"1",variant:i?"solid":"soft",color:i?"blue":"gray",style:{color:"#ffffff",cursor:"pointer"},onClick:o,children:[e.jsx(V,{}),e.jsxs(f,{size:"1",style:{fontSize:11},children:["Team (",(s==null?void 0:s.total)||0,")"]})]}),e.jsx(N,{size:"1",variant:"soft",color:"gray",style:{color:"#ffffff",background:"rgba(255, 255, 255, 0.12)",cursor:"pointer"},title:p?"Exit Fullscreen":"Enter Fullscreen",onClick:r,children:p?e.jsx(et,{}):e.jsx(pe,{})})]})]})})});Ee.displayName="MapHudControls";L.Control.Fullscreen=L.Control.extend({options:{position:"topleft",title:{false:"View Fullscreen",true:"Exit Fullscreen"}},onAdd:function(t){var c=L.DomUtil.create("div","leaflet-control-fullscreen leaflet-bar leaflet-control");return this.link=L.DomUtil.create("a","leaflet-control-fullscreen-button leaflet-bar-part",c),this.link.href="#",this._map=t,this._map.on("fullscreenchange",this._toggleTitle,this),this._toggleTitle(),L.DomEvent.on(this.link,"click",this._click,this),c},_click:function(t){L.DomEvent.stopPropagation(t),L.DomEvent.preventDefault(t),this._map.toggleFullscreen(this.options)},_toggleTitle:function(){this.link.title=this.options.title[this._map.isFullscreen()]}});L.Map.include({isFullscreen:function(){return this._isFullscreen||!1},toggleFullscreen:function(t){var c=this.getContainer();this.isFullscreen()?t&&t.pseudoFullscreen?this._disablePseudoFullscreen(c):document.exitFullscreen?document.exitFullscreen():document.mozCancelFullScreen?document.mozCancelFullScreen():document.webkitCancelFullScreen?document.webkitCancelFullScreen():document.msExitFullscreen?document.msExitFullscreen():this._disablePseudoFullscreen(c):t&&t.pseudoFullscreen?this._enablePseudoFullscreen(c):c.requestFullscreen?c.requestFullscreen():c.mozRequestFullScreen?c.mozRequestFullScreen():c.webkitRequestFullscreen?c.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT):c.msRequestFullscreen?c.msRequestFullscreen():this._enablePseudoFullscreen(c)},_enablePseudoFullscreen:function(t){L.DomUtil.addClass(t,"leaflet-pseudo-fullscreen"),this._setFullscreen(!0),this.fire("fullscreenchange")},_disablePseudoFullscreen:function(t){L.DomUtil.removeClass(t,"leaflet-pseudo-fullscreen"),this._setFullscreen(!1),this.fire("fullscreenchange")},_setFullscreen:function(t){this._isFullscreen=t;var c=this.getContainer();t?L.DomUtil.addClass(c,"leaflet-fullscreen-on"):L.DomUtil.removeClass(c,"leaflet-fullscreen-on"),this.invalidateSize()},_onFullscreenChange:function(t){var c=document.fullscreenElement||document.mozFullScreenElement||document.webkitFullscreenElement||document.msFullscreenElement;c===this.getContainer()&&!this._isFullscreen?(this._setFullscreen(!0),this.fire("fullscreenchange")):c!==this.getContainer()&&this._isFullscreen&&(this._setFullscreen(!1),this.fire("fullscreenchange"))}});L.Map.mergeOptions({fullscreenControl:!1});L.Map.addInitHook(function(){this.options.fullscreenControl&&(this.fullscreenControl=new L.Control.Fullscreen(this.options.fullscreenControl),this.addControl(this.fullscreenControl));var t;if("onfullscreenchange"in document?t="fullscreenchange":"onmozfullscreenchange"in document?t="mozfullscreenchange":"onwebkitfullscreenchange"in document?t="webkitfullscreenchange":"onmsfullscreenchange"in document&&(t="MSFullscreenChange"),t){var c=L.bind(this._onFullscreenChange,this);this.whenReady(function(){L.DomEvent.on(document,t,c)}),this.on("unload",function(){L.DomEvent.off(document,t,c)})}});L.control.fullscreen=function(t){return new L.Control.Fullscreen(t)};const Ie=A.memo(({fitBoundsTrigger:t,users:c,flyToCoords:d,attendanceTypeConfigs:w})=>{const s=fe();return u.useEffect(()=>{if(!s||t===0)return;const C=P.latLngBounds([]);(c||[]).forEach(h=>{const a=h.punchin_location||h.location,y=h.punchout_location;a&&a.lat&&a.lng&&C.extend([parseFloat(a.lat),parseFloat(a.lng)]),y&&y.lat&&y.lng&&C.extend([parseFloat(y.lat),parseFloat(y.lng)])}),(w||[]).forEach(h=>{var a,y;(a=h.config)!=null&&a.polygon&&h.config.polygon.forEach(j=>{j.lat&&j.lng&&C.extend([parseFloat(j.lat),parseFloat(j.lng)])}),(y=h.config)!=null&&y.waypoints&&h.config.waypoints.forEach(j=>{j.lat&&j.lng&&C.extend([parseFloat(j.lat),parseFloat(j.lng)])})}),C.isValid()&&s.fitBounds(C,{padding:[60,60],maxZoom:15,animate:!0,duration:.8})},[s,t,c,w]),u.useEffect(()=>{!s||!d||s.flyTo(d,16,{animate:!0,duration:1.2})},[s,d]),null});Ie.displayName="MapController";const Re=A.memo(({currentTileId:t="voyager",users:c=[],attendanceTypeConfigs:d=[],fitBoundsTrigger:w=0,flyToCoords:s=null,children:C})=>{const h=oe[t]||oe.voyager;return u.useEffect(()=>{const a="team-map-injected-styles";if(!document.getElementById(a)){const y=document.createElement("style");y.id=a,y.innerHTML=ct,document.head.appendChild(y)}},[]),e.jsx("div",{style:{position:"relative",width:"100%",height:"100%"},children:e.jsxs(ot,{center:it,zoom:st,minZoom:at,maxZoom:lt,style:{width:"100%",height:"100%",background:"#0f172a"},scrollWheelZoom:!0,doubleClickZoom:!0,dragging:!0,touchZoom:!0,zoomControl:!1,attributionControl:!1,children:[e.jsx(nt,{url:h.url,subdomains:h.subdomains,maxZoom:h.maxZoom,attribution:h.attribution},h.id),e.jsx(Ie,{fitBoundsTrigger:w,users:c,flyToCoords:s,attendanceTypeConfigs:d}),C]})})});Re.displayName="MapContainerView";const Me=A.memo(({attendanceTypeConfigs:t=[],users:c=[],layerVisibility:d={geofences:!0,waypoints:!0,trajectories:!0}})=>{const w=fe(),s=u.useRef([]);return u.useEffect(()=>{if(!w||(s.current.forEach(h=>{try{w.removeLayer(h)}catch{}}),s.current=[],!t||t.length===0))return;const C=["#3b82f6","#10b981","#f59e0b","#8b5cf6","#ec4899","#06b6d4"];return t.forEach((h,a)=>{const{base_slug:y,config:j,name:l}=h,v=C[a%C.length];if(y==="geo_polygon"&&j&&d.geofences){const i=j.polygon||[],o=j.polygons||[],p=(r,x)=>{const b=r.filter(g=>g&&g.lat&&g.lng);if(b.length<3)return;const _=b.map(g=>[parseFloat(g.lat),parseFloat(g.lng)]),F=P.polygon(_,{color:v,fillColor:v,fillOpacity:.16,weight:2.5,opacity:.85,dashArray:"6, 6"}).addTo(w),S=F.getBounds(),I=S.getCenter(),z=c.filter(g=>{const T=g.punchin_location||g.punchout_location||g.location;if(!T||!T.lat||!T.lng)return!1;const $=P.latLng(parseFloat(T.lat),parseFloat(T.lng));return S.contains($)}).length,E=`
                        <div class="geofence-centroid-badge" style="border-color: ${v}66;">
                            <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${v};"></span>
                            <span>${x||l}</span>
                            ${z>0?`<span style="background:${v}; color:white; border-radius:10px; padding:0 6px; font-size:10px;">${z} Officers</span>`:""}
                        </div>
                    `,M=P.marker(I,{icon:P.divIcon({html:E,className:"geofence-label-marker",iconSize:[120,26],iconAnchor:[60,13]}),interactive:!1}).addTo(w);F.bindPopup(`
                        <div style="font-family: inherit; padding: 6px; min-width: 140px; color: #1e293b;">
                            <div style="font-weight: 700; color: ${v}; font-size: 13px; margin-bottom: 2px;">
                                🛡️ ${x||l}
                            </div>
                            <div style="font-size: 11px; color: #64748b;">Geofence Zone Perimeter</div>
                            <div style="font-size: 11px; margin-top: 4px; font-weight: 600;">
                                Verified Officers: <span style="color:${v};">${z}</span>
                            </div>
                        </div>
                    `),s.current.push(F),s.current.push(M)};i.length>=3&&p(i,l),o.forEach((r,x)=>{r.points&&r.points.length>=3&&p(r.points,r.name||`${l} Zone ${x+1}`)})}if(y==="route_waypoint"&&j&&d.waypoints){const o=(j.waypoints||[]).filter(p=>p&&p.lat&&p.lng);if(o.length>=2){const p=o.map(x=>[parseFloat(x.lat),parseFloat(x.lng)]),r=P.polyline(p,{color:v,weight:3.5,opacity:.75,dashArray:"8, 6"}).addTo(w);s.current.push(r),o.forEach((x,b)=>{const _=b===0,F=b===o.length-1,I=`
                            <div style="
                                width: 26px;
                                height: 26px;
                                border-radius: 50%;
                                background: ${_?"#10b981":F?"#ef4444":v};
                                border: 2px solid #ffffff;
                                box-shadow: 0 3px 8px rgba(0,0,0,0.35);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                color: white;
                                font-weight: 800;
                                font-size: 11px;
                            ">
                                ${_?"S":F?"E":b+1}
                            </div>
                        `,z=P.marker([parseFloat(x.lat),parseFloat(x.lng)],{icon:P.divIcon({html:I,className:"waypoint-marker",iconSize:[26,26],iconAnchor:[13,13]})}).addTo(w);z.bindPopup(`
                            <div style="font-family: inherit; padding: 4px; color: #1e293b;">
                                <strong style="color: ${v};">${l}</strong><br>
                                <span style="font-size: 11px; color: #64748b;">
                                    ${_?"🚀 Route Start Point":F?"🏁 Route End Point":`Waypoint #${b+1}`}
                                </span>
                            </div>
                        `),s.current.push(z)})}}}),()=>{s.current.forEach(h=>{try{w.removeLayer(h)}catch{}}),s.current=[]}},[w,t,c,d]),null});Me.displayName="MapGeofenceLayers";const Te=A.memo(({users:t=[],selectedUserId:c,onSelectOfficer:d,onOpenTelemetry:w,onOpenPhoto:s,layerVisibility:C={trajectories:!0}})=>{const h=fe(),a=u.useRef([]),y=u.useRef([]),j=u.useCallback(i=>{if(!i)return null;if(typeof i=="object"&&i.lat&&i.lng){const o=parseFloat(i.lat),p=parseFloat(i.lng);if(!isNaN(o)&&!isNaN(p))return{lat:o,lng:p}}if(typeof i=="string")try{const o=JSON.parse(i);if(o.lat&&o.lng){const p=parseFloat(o.lat),r=parseFloat(o.lng);if(!isNaN(p)&&!isNaN(r))return{lat:p,lng:r}}}catch{const p=i.split(",");if(p.length>=2){const r=parseFloat(p[0].trim()),x=parseFloat(p[1].trim());if(!isNaN(r)&&!isNaN(x))return{lat:r,lng:x}}}return null},[]),l=u.useCallback((i,o="active",p=!1)=>{var M,g;const r=i.status==="active"||o==="punchin",x=o==="punchout",b=i.profile_image_url,_=((g=(M=i.name)==null?void 0:M.charAt(0))==null?void 0:g.toUpperCase())||"?",F=r?'<div class="living-marker-radar-ring"></div>':"",S=`living-marker-core ${r?"is-active":x?"is-punchout":"is-completed"}`,I=r?U.active:x?U.punchout:U.completed,z=r?"▶":x?"◼":"✓",E=`
            <div class="living-marker-wrapper" style="${p?"transform: scale(1.25); z-index: 9999;":""}">
                ${F}
                <div class="${S}" style="${p?"border-color: #38bdf8; box-shadow: 0 0 16px #38bdf8;":""}">
                    ${b?`<img src="${b}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; this.parentElement.innerText='${_}';" />`:_}
                </div>
                <div class="living-marker-badge" style="background: ${I};">
                    ${z}
                </div>
            </div>
        `;return P.divIcon({html:E,className:"custom-living-marker",iconSize:[44,44],iconAnchor:[22,22],popupAnchor:[0,-22]})},[]),v=u.useCallback((i,o,p="current")=>{var z,E;const r=i.status==="active",x=(o==null?void 0:o.punchin_time)||i.punchin_time||"--",b=(o==null?void 0:o.punchout_time)||i.punchout_time,_=(o==null?void 0:o.punchin_photo_url)||i.punchin_photo_url,F=(o==null?void 0:o.punchout_photo_url)||i.punchout_photo_url,S=p==="punchout"&&F||_,I=S?`
            <div style="margin: 8px 0; border-radius: 6px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15); max-height: 90px; cursor: pointer; position: relative;"
                 onclick="window.__openMapPhoto && window.__openMapPhoto('${S}', '${i.name.replace(/'/g,"\\'")}', '${x}', '${p}')">
                <img src="${S}" style="width: 100%; height: 85px; object-fit: cover;" alt="Selfie" />
                <div style="position: absolute; bottom: 2px; right: 4px; background: rgba(0,0,0,0.65); padding: 1px 6px; border-radius: 4px; font-size: 9px; color: #fff;">
                    🔍 Zoom
                </div>
            </div>
        `:"";return`
            <div style="
                min-width: 200px;
                max-width: 240px;
                background: rgba(15, 23, 42, 0.92);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 12px;
                padding: 12px;
                color: #ffffff;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
                font-family: inherit;
            ">
                <!-- Header -->
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <div style="
                        width: 32px;
                        height: 32px;
                        border-radius: 50%;
                        overflow: hidden;
                        border: 2px solid ${r?U.active:"#64748b"};
                        background: #1e293b;
                        color: white;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-weight: bold;
                        font-size: 12px;
                        flex-shrink: 0;
                    ">
                        ${i.profile_image_url?`<img src="${i.profile_image_url}" style="width:100%; height:100%; object-fit:cover;" />`:((E=(z=i.name)==null?void 0:z.charAt(0))==null?void 0:E.toUpperCase())||"?"}
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 700; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #ffffff;">
                            ${i.name}
                        </div>
                        <div style="font-size: 10px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            ${i.designation||"Officer"}
                        </div>
                    </div>
                    <div style="
                        font-size: 9px;
                        font-weight: 600;
                        padding: 2px 6px;
                        border-radius: 10px;
                        background: ${r?"rgba(16, 185, 129, 0.2)":"rgba(59, 130, 246, 0.2)"};
                        color: ${r?"#34d399":"#60a5fa"};
                        border: 1px solid ${r?"rgba(16, 185, 129, 0.4)":"rgba(59, 130, 246, 0.4)"};
                    ">
                        ${r?"🟢 ACTIVE":"✅ DONE"}
                    </div>
                </div>

                <!-- Timestamps -->
                <div style="background: rgba(255, 255, 255, 0.06); border-radius: 6px; padding: 6px; margin-bottom: 6px; font-size: 11px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 3px;">
                        <span style="color: #94a3b8;">Check In:</span>
                        <span style="font-weight: 600; color: #34d399;">${x}</span>
                    </div>
                    ${b?`
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="color: #94a3b8;">Check Out:</span>
                        <span style="font-weight: 600; color: #f87171;">${b}</span>
                    </div>`:""}
                </div>

                ${I}

                <!-- Inspect Button -->
                <button
                    onclick="window.__inspectOfficer && window.__inspectOfficer(${i.user_id})"
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
        `},[]);return u.useEffect(()=>(window.__inspectOfficer=i=>{const o=t.find(p=>p.user_id===i);o&&w&&w(o)},window.__openMapPhoto=(i,o,p,r)=>{s&&s({url:i,officerName:o,timestamp:p,type:r})},()=>{delete window.__inspectOfficer,delete window.__openMapPhoto}),[t,w,s]),u.useEffect(()=>{if(!h||(a.current.forEach(r=>{try{h.removeLayer(r)}catch{}}),a.current=[],y.current.forEach(r=>{try{h.removeLayer(r)}catch{}}),y.current=[],!t||t.length===0))return;const i=[],o=15e-5,p=r=>{let x=r.lat,b=r.lng;const _=i.filter(F=>Math.abs(F.lat-x)<o&&Math.abs(F.lng-b)<o).length;if(_>0){const F=_*1.25,S=18e-5*Math.sqrt(_);x+=Math.cos(F)*S,b+=Math.sin(F)*S}return i.push({lat:x,lng:b}),{lat:x,lng:b}};return t.forEach(r=>{const x=r.cycles&&r.cycles.length>0?r.cycles:null,b=c===r.user_id;if(x)x.forEach((_,F)=>{const S=j(_.punchin_location),I=j(_.punchout_location);if(S&&I&&_.is_complete){const z=p(S),E=p(I),M=P.marker([z.lat,z.lng],{icon:l(r,"punchin",b),zIndexOffset:b?1e3:100}).addTo(h);M.bindPopup(v(r,_,"punchin")),M.on("click",()=>d&&d(r)),a.current.push(M);const g=P.marker([E.lat,E.lng],{icon:l(r,"punchout",b),zIndexOffset:b?1e3:90}).addTo(h);if(g.bindPopup(v(r,_,"punchout")),g.on("click",()=>d&&d(r)),a.current.push(g),C.trajectories){const T=P.polyline([[z.lat,z.lng],[E.lat,E.lng]],{color:"#06b6d4",weight:3.5,opacity:.8,className:"patrol-trajectory-path"}).addTo(h);y.current.push(T)}}else{const z=S||I;if(z){const E=p(z),M=P.marker([E.lat,E.lng],{icon:l(r,r.status,b),zIndexOffset:b?1e3:150}).addTo(h);M.bindPopup(v(r,_,"punchin")),M.on("click",()=>d&&d(r)),a.current.push(M)}}});else{const _=j(r.punchin_location||r.location),F=j(r.punchout_location),S=_||F;if(S){const I=p(S),z=P.marker([I.lat,I.lng],{icon:l(r,r.status,b),zIndexOffset:b?1e3:100}).addTo(h);if(z.bindPopup(v(r,r,r.status)),z.on("click",()=>d&&d(r)),a.current.push(z),_&&F&&r.punchout_time&&C.trajectories){const E=p(F),M=P.polyline([[I.lat,I.lng],[E.lat,E.lng]],{color:"#06b6d4",weight:3.5,opacity:.8,className:"patrol-trajectory-path"}).addTo(h);y.current.push(M)}}}}),()=>{a.current.forEach(r=>{try{h.removeLayer(r)}catch{}}),a.current=[],y.current.forEach(r=>{try{h.removeLayer(r)}catch{}}),y.current=[]}},[h,t,c,C,l,v,j,d]),null});Te.displayName="MapLivingMarkers";const Pe=A.memo(({isOpen:t,onClose:c,users:d=[],selectedUserId:w,onSelectOfficer:s,onOpenTelemetry:C,onOpenPhoto:h})=>{const[a,y]=u.useState(""),j=u.useMemo(()=>{if(!a)return d;const l=a.toLowerCase();return d.filter(v=>{var i,o,p,r;return((i=v.name)==null?void 0:i.toLowerCase().includes(l))||((o=v.employee_id)==null?void 0:o.toLowerCase().includes(l))||((p=v.designation)==null?void 0:p.toLowerCase().includes(l))||((r=v.department)==null?void 0:r.toLowerCase().includes(l))})},[d,a]);return t?e.jsxs(m,{style:{position:"absolute",top:74,right:14,bottom:14,width:320,maxWidth:"calc(100vw - 28px)",background:"rgba(15, 23, 42, 0.92)",backdropFilter:"blur(20px)",WebkitBackdropFilter:"blur(20px)",borderRadius:"var(--radius-4)",border:"1px solid rgba(255, 255, 255, 0.15)",boxShadow:"0 20px 40px rgba(0, 0, 0, 0.5)",zIndex:1e3,display:"flex",flexDirection:"column",overflow:"hidden",animation:"slideInRight 0.25s cubic-bezier(0.16, 1, 0.3, 1)"},children:[e.jsxs(m,{p:"3",style:{borderBottom:"1px solid rgba(255, 255, 255, 0.1)",background:"rgba(0, 0, 0, 0.25)"},children:[e.jsxs(n,{justify:"between",align:"center",mb:"2",children:[e.jsxs(n,{align:"center",gap:"2",children:[e.jsx(V,{style:{color:"#38bdf8",width:16,height:16}}),e.jsx(f,{size:"2",weight:"bold",style:{color:"#ffffff"},children:"On-Duty Team Roster"}),e.jsx(Z,{size:"1",color:"blue",variant:"solid",radius:"full",children:d.length})]}),e.jsx(N,{size:"1",variant:"ghost",style:{color:"#94a3b8",cursor:"pointer"},onClick:c,children:e.jsx(J,{})})]}),e.jsxs(ze,{size:"1",placeholder:"Filter roster...",value:a,onChange:l=>y(l.target.value),style:{background:"rgba(255, 255, 255, 0.08)",color:"#ffffff",border:"1px solid rgba(255, 255, 255, 0.15)"},children:[e.jsx(te,{children:e.jsx(Fe,{style:{color:"#94a3b8"}})}),a&&e.jsx(te,{children:e.jsx(N,{size:"1",variant:"ghost",style:{color:"#cbd5e1",cursor:"pointer"},onClick:()=>y(""),children:e.jsx(J,{})})})]})]}),e.jsx(m,{p:"2",style:{flex:1,overflowY:"auto",display:"flex",flexDirection:"column",gap:8},children:j.length>0?j.map(l=>{var o,p;const v=w===l.user_id,i=l.status==="active";return l.punchout_photo_url||l.punchin_photo_url,e.jsx(m,{p:"2",style:{background:v?"rgba(56, 189, 248, 0.18)":"rgba(255, 255, 255, 0.05)",borderRadius:"var(--radius-3)",border:v?"1px solid rgba(56, 189, 248, 0.5)":"1px solid rgba(255, 255, 255, 0.08)",cursor:"pointer",transition:"all 0.15s ease"},onMouseEnter:r=>{v||(r.currentTarget.style.background="rgba(255, 255, 255, 0.1)")},onMouseLeave:r=>{v||(r.currentTarget.style.background="rgba(255, 255, 255, 0.05)")},onClick:()=>s(l),children:e.jsxs(n,{justify:"between",align:"start",gap:"2",children:[e.jsxs(n,{align:"start",gap:"2",style:{flex:1,minWidth:0},children:[e.jsx(m,{style:{position:"relative",width:36,height:36,borderRadius:"50%",overflow:"hidden",border:`2px solid ${i?U.active:"#64748b"}`,background:"#1e293b",color:"white",display:"flex",alignItems:"center",justifyContent:"center",fontSize:12,fontWeight:"bold",flexShrink:0},children:l.profile_image_url?e.jsx("img",{src:l.profile_image_url,alt:l.name,style:{width:"100%",height:"100%",objectFit:"cover"}}):((p=(o=l.name)==null?void 0:o.charAt(0))==null?void 0:p.toUpperCase())||"?"}),e.jsxs(m,{style:{flex:1,minWidth:0},children:[e.jsx(n,{align:"center",gap:"1",children:e.jsx(f,{size:"2",weight:"bold",style:{color:"#ffffff",whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis"},children:l.name})}),e.jsx(f,{size:"1",style:{color:"#94a3b8",fontSize:11,whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis",display:"block"},children:l.designation||"Officer"}),e.jsxs(n,{align:"center",gap:"2",mt:"1",wrap:"wrap",children:[l.punchin_time&&e.jsxs(n,{align:"center",gap:"1",children:[e.jsx(Q,{style:{color:"#34d399",width:10,height:10}}),e.jsxs(f,{size:"1",style:{color:"#cbd5e1",fontSize:10},children:["In: ",l.punchin_time]})]}),l.punchout_time&&e.jsxs(n,{align:"center",gap:"1",children:[e.jsx(ge,{style:{color:"#f87171",width:10,height:10}}),e.jsxs(f,{size:"1",style:{color:"#cbd5e1",fontSize:10},children:["Out: ",l.punchout_time]})]})]})]})]}),e.jsxs(n,{direction:"column",align:"end",gap:"1",children:[e.jsx(Z,{size:"1",color:i?"green":"blue",variant:"solid",radius:"full",style:{fontSize:9},children:i?"Live":"Done"}),e.jsx(N,{size:"1",variant:"ghost",style:{color:"#38bdf8",cursor:"pointer",height:20,width:20},title:"Inspect full telemetry",onClick:r=>{r.stopPropagation(),C(l)},children:e.jsx(tt,{})})]})]})},l.user_id)}):e.jsxs(n,{direction:"column",align:"center",justify:"center",p:"4",gap:"2",children:[e.jsx(V,{style:{color:"#64748b",width:28,height:28}}),e.jsx(f,{size:"1",style:{color:"#94a3b8"},children:a?"No matching officers found":"No officers tracked"})]})})]}):null});Pe.displayName="MapTeamRosterDrawer";const $e=A.memo(({officer:t,selectedDate:c,onClose:d,onOpenPhoto:w,onFocusMap:s})=>{var M;const[C,h]=u.useState(null);if(!t)return null;const{name:a,employee_id:y,designation:j,department:l,profile_image_url:v,status:i,cycles:o=[],punchin_time:p,punchout_time:r,punchin_location:x,punchout_location:b,punchin_photo_url:_,punchout_photo_url:F,attendance_type:S}=t,I=i==="active",z=(g,T)=>{g&&(navigator.clipboard.writeText(g),h(T),setTimeout(()=>h(null),2e3))},E=o&&o.length>0?o:[{attendance_id:"default",punchin_time:p,punchout_time:r,punchin_location:x,punchout_location:b,punchin_photo_url:_,punchout_photo_url:F,is_complete:!!r}];return e.jsx(m,{style:{position:"fixed",inset:0,background:"rgba(5, 10, 20, 0.75)",backdropFilter:"blur(8px)",WebkitBackdropFilter:"blur(8px)",zIndex:99990,display:"flex",alignItems:"center",justifyContent:"center",padding:16,animation:"fadeIn 0.2s ease-out"},onClick:d,children:e.jsxs(m,{style:{width:"100%",maxWidth:580,maxHeight:"90vh",background:"var(--color-panel-solid, #1e293b)",borderRadius:"var(--radius-4)",border:"1px solid var(--gray-a6)",boxShadow:"0 25px 60px -15px rgba(0,0,0,0.5)",display:"flex",flexDirection:"column",overflow:"hidden"},onClick:g=>g.stopPropagation(),children:[e.jsx(m,{p:"4",style:{background:"linear-gradient(135deg, var(--gray-a3), var(--gray-a4))",borderBottom:"1px solid var(--gray-a5)"},children:e.jsxs(n,{justify:"between",align:"start",children:[e.jsxs(n,{align:"center",gap:"3",children:[e.jsx(m,{style:{width:52,height:52,borderRadius:"50%",overflow:"hidden",border:`3px solid ${I?U.active:"var(--gray-a7)"}`,background:"var(--gray-a4)",display:"flex",alignItems:"center",justifyContent:"center",color:"white",fontWeight:"bold",fontSize:18,flexShrink:0,boxShadow:I?"0 0 12px rgba(16, 185, 129, 0.4)":"none"},children:v?e.jsx("img",{src:v,alt:a,style:{width:"100%",height:"100%",objectFit:"cover"}}):((M=a==null?void 0:a.charAt(0))==null?void 0:M.toUpperCase())||"?"}),e.jsxs(m,{children:[e.jsxs(n,{align:"center",gap:"2",wrap:"wrap",children:[e.jsx(f,{size:"3",weight:"bold",style:{color:"var(--gray-12)"},children:a||"Officer"}),e.jsx(Z,{size:"1",color:I?"green":"blue",variant:"solid",radius:"full",children:I?"🟢 Active On-Duty":"✅ Shift Completed"})]}),e.jsxs(f,{size:"1",color:"gray",children:[j||"Employee"," ",l?`• ${l}`:"",y?` • ID: ${y}`:""]}),S&&e.jsxs(Z,{size:"1",color:"purple",variant:"soft",mt:"1",children:["Zone: ",S.name||"Standard"]})]})]}),e.jsx(N,{size:"2",variant:"ghost",color:"gray",onClick:d,style:{cursor:"pointer"},children:e.jsx(J,{})})]})}),e.jsxs(m,{p:"4",style:{overflowY:"auto",flex:1,display:"flex",flexDirection:"column",gap:16},children:[e.jsxs(n,{justify:"between",align:"center",children:[e.jsxs(f,{size:"2",weight:"bold",style:{color:"var(--gray-11)"},children:["Attendance & Patrol Telemetry (",E.length," ",E.length===1?"Cycle":"Cycles",")"]}),e.jsxs(f,{size:"1",color:"gray",children:["Date: ",c||"Today"]})]}),E.map((g,T)=>{const $=g.punchin_location,G=g.punchout_location,B=$&&$.lat&&$.lng?`${parseFloat($.lat).toFixed(5)}, ${parseFloat($.lng).toFixed(5)}`:null,Y=G&&G.lat&&G.lng?`${parseFloat(G.lat).toFixed(5)}, ${parseFloat(G.lng).toFixed(5)}`:null;return e.jsxs(m,{p:"3",style:{background:"var(--gray-a2)",borderRadius:"var(--radius-3)",border:"1px solid var(--gray-a4)"},children:[e.jsxs(n,{justify:"between",align:"center",mb:"3",children:[e.jsxs(Z,{size:"1",color:"gray",variant:"surface",children:["Shift Cycle #",T+1]}),e.jsx(Z,{size:"1",color:g.is_complete?"blue":"green",variant:"soft",children:g.is_complete?"Cycle Finished":"Active Cycle"})]}),e.jsxs(n,{direction:"column",gap:"3",children:[e.jsxs(n,{align:"start",justify:"between",p:"2",style:{background:"var(--green-a2)",borderRadius:"var(--radius-2)",border:"1px solid var(--green-a4)"},children:[e.jsxs(n,{align:"start",gap:"2",style:{flex:1},children:[e.jsx(m,{style:{width:24,height:24,borderRadius:"50%",background:U.punchin,color:"white",display:"flex",alignItems:"center",justifyContent:"center",flexShrink:0},children:e.jsx(Q,{style:{width:14,height:14}})}),e.jsxs(m,{children:[e.jsxs(f,{size:"1",weight:"bold",style:{color:"var(--green-11)"},children:["Check-In: ",g.punchin_time||"--"]}),B?e.jsxs(n,{align:"center",gap:"1",mt:"1",children:[e.jsx(ue,{style:{color:"var(--green-9)",width:12,height:12}}),e.jsx(f,{size:"1",style:{fontSize:11,fontFamily:"monospace",color:"var(--gray-11)"},children:B}),e.jsx(N,{size:"1",variant:"ghost",style:{height:18,width:18},onClick:()=>z(B,`in-${T}`),children:C===`in-${T}`?e.jsx(re,{}):e.jsx(he,{})})]}):e.jsx(f,{size:"1",color:"gray",style:{fontSize:11},children:"No GPS coordinates"})]})]}),g.punchin_photo_url&&e.jsxs(m,{style:{width:48,height:48,borderRadius:"var(--radius-2)",overflow:"hidden",border:"1px solid var(--green-a6)",cursor:"pointer",position:"relative",flexShrink:0},onClick:()=>w&&w({url:g.punchin_photo_url,officerName:a,designation:j,timestamp:g.punchin_time,location:$,type:"punchin"}),children:[e.jsx("img",{src:g.punchin_photo_url,alt:"Check-in selfie",style:{width:"100%",height:"100%",objectFit:"cover"}}),e.jsx(m,{style:{position:"absolute",bottom:0,insetInline:0,background:"rgba(0,0,0,0.6)",display:"flex",alignItems:"center",justifyContent:"center",padding:1},children:e.jsx(pe,{style:{color:"white",width:10,height:10}})})]})]}),g.punchout_time?e.jsxs(n,{align:"start",justify:"between",p:"2",style:{background:"var(--red-a2)",borderRadius:"var(--radius-2)",border:"1px solid var(--red-a4)"},children:[e.jsxs(n,{align:"start",gap:"2",style:{flex:1},children:[e.jsx(m,{style:{width:24,height:24,borderRadius:"50%",background:U.punchout,color:"white",display:"flex",alignItems:"center",justifyContent:"center",flexShrink:0},children:e.jsx(ge,{style:{width:14,height:14}})}),e.jsxs(m,{children:[e.jsxs(f,{size:"1",weight:"bold",style:{color:"var(--red-11)"},children:["Check-Out: ",g.punchout_time]}),Y?e.jsxs(n,{align:"center",gap:"1",mt:"1",children:[e.jsx(ue,{style:{color:"var(--red-9)",width:12,height:12}}),e.jsx(f,{size:"1",style:{fontSize:11,fontFamily:"monospace",color:"var(--gray-11)"},children:Y}),e.jsx(N,{size:"1",variant:"ghost",style:{height:18,width:18},onClick:()=>z(Y,`out-${T}`),children:C===`out-${T}`?e.jsx(re,{}):e.jsx(he,{})})]}):e.jsx(f,{size:"1",color:"gray",style:{fontSize:11},children:"No GPS coordinates"})]})]}),g.punchout_photo_url&&e.jsxs(m,{style:{width:48,height:48,borderRadius:"var(--radius-2)",overflow:"hidden",border:"1px solid var(--red-a6)",cursor:"pointer",position:"relative",flexShrink:0},onClick:()=>w&&w({url:g.punchout_photo_url,officerName:a,designation:j,timestamp:g.punchout_time,location:G,type:"punchout"}),children:[e.jsx("img",{src:g.punchout_photo_url,alt:"Check-out selfie",style:{width:"100%",height:"100%",objectFit:"cover"}}),e.jsx(m,{style:{position:"absolute",bottom:0,insetInline:0,background:"rgba(0,0,0,0.6)",display:"flex",alignItems:"center",justifyContent:"center",padding:1},children:e.jsx(pe,{style:{color:"white",width:10,height:10}})})]})]}):e.jsxs(n,{align:"center",gap:"2",p:"2",style:{background:"var(--gray-a3)",borderRadius:"var(--radius-2)",border:"1px dashed var(--gray-a5)"},children:[e.jsx(Q,{style:{color:"var(--amber-9)"}}),e.jsx(f,{size:"1",color:"gray",children:"Officer is currently on active patrol. Check-out not recorded yet."})]})]})]},T)})]}),e.jsx(m,{p:"3",style:{background:"var(--gray-a2)",borderTop:"1px solid var(--gray-a4)"},children:e.jsxs(n,{justify:"between",align:"center",gap:"2",children:[e.jsxs(O,{variant:"surface",color:"blue",size:"2",onClick:()=>{if(d(),s){const g=x||b;g&&g.lat&&g.lng&&s([parseFloat(g.lat),parseFloat(g.lng)])}},children:[e.jsx(Se,{})," Focus on Map"]}),e.jsx(O,{variant:"outline",color:"gray",size:"2",onClick:d,children:"Close"})]})})]})})});$e.displayName="OfficerDetailModal";const Oe=A.memo(({photoData:t,onClose:c})=>{const[d,w]=A.useState(!1);if(!t||!t.url)return null;const{url:s,title:C,officerName:h,designation:a,timestamp:y,location:j,type:l}=t,v=j&&j.lat&&j.lng?`${parseFloat(j.lat).toFixed(6)}, ${parseFloat(j.lng).toFixed(6)}`:null,i=()=>{v&&(navigator.clipboard.writeText(v),w(!0),setTimeout(()=>w(!1),2e3))};return e.jsxs(m,{style:{position:"fixed",inset:0,background:"rgba(5, 10, 20, 0.94)",backdropFilter:"blur(16px)",WebkitBackdropFilter:"blur(16px)",zIndex:99999,display:"flex",alignItems:"center",justifyContent:"center",padding:24,animation:"fadeIn 0.2s ease-out"},onClick:c,children:[e.jsx("button",{style:{position:"absolute",top:24,right:24,width:44,height:44,borderRadius:"50%",background:"rgba(255, 255, 255, 0.12)",border:"1px solid rgba(255, 255, 255, 0.25)",color:"white",cursor:"pointer",display:"flex",alignItems:"center",justifyContent:"center",transition:"all 0.15s ease",zIndex:10},onMouseEnter:o=>{o.currentTarget.style.background="rgba(255,255,255,0.25)"},onMouseLeave:o=>{o.currentTarget.style.background="rgba(255,255,255,0.12)"},onClick:o=>{o.stopPropagation(),c()},"aria-label":"Close photo preview",children:e.jsx(J,{style:{width:22,height:22}})}),e.jsxs(m,{style:{maxWidth:"90vw",maxHeight:"90vh",display:"flex",flexDirection:"column",alignItems:"center",background:"rgba(15, 23, 42, 0.9)",border:"1px solid rgba(255, 255, 255, 0.15)",borderRadius:"var(--radius-4)",boxShadow:"0 25px 60px -15px rgba(0, 0, 0, 0.8)",overflow:"hidden"},onClick:o=>o.stopPropagation(),children:[e.jsx(m,{p:"3",style:{width:"100%",borderBottom:"1px solid rgba(255, 255, 255, 0.1)",background:"rgba(0, 0, 0, 0.3)"},children:e.jsxs(n,{justify:"between",align:"center",gap:"3",px:"2",children:[e.jsxs(n,{align:"center",gap:"2",children:[e.jsx(V,{style:{color:"#38bdf8",width:18,height:18}}),e.jsxs(m,{children:[e.jsx(f,{size:"2",weight:"bold",style:{color:"#ffffff"},children:h||"Officer Photo"}),a&&e.jsx(f,{size:"1",style:{color:"#94a3b8",display:"block"},children:a})]})]}),e.jsx(Z,{size:"1",color:l==="punchin"?"green":l==="punchout"?"red":"blue",variant:"solid",children:l==="punchin"?"Check-In Photo":l==="punchout"?"Check-Out Photo":C||"Selfie"})]})}),e.jsx(m,{style:{display:"flex",alignItems:"center",justifyContent:"center",padding:16,maxHeight:"65vh",minWidth:320,maxWidth:720,overflow:"hidden"},children:e.jsx("img",{src:s,alt:"Officer Telemetry Verification",style:{maxWidth:"100%",maxHeight:"60vh",objectFit:"contain",borderRadius:"var(--radius-3)",border:"1px solid rgba(255, 255, 255, 0.1)",boxShadow:"0 8px 30px rgba(0,0,0,0.5)"}})}),e.jsx(m,{p:"3",style:{width:"100%",borderTop:"1px solid rgba(255, 255, 255, 0.1)",background:"rgba(0, 0, 0, 0.4)"},children:e.jsxs(n,{justify:"between",align:"center",gap:"3",wrap:"wrap",px:"2",children:[e.jsxs(n,{align:"center",gap:"4",wrap:"wrap",children:[y&&e.jsxs(n,{align:"center",gap:"1",children:[e.jsx(Q,{style:{color:"#a78bfa",width:14,height:14}}),e.jsx(f,{size:"1",style:{color:"#cbd5e1"},children:y})]}),v&&e.jsxs(n,{align:"center",gap:"2",children:[e.jsx(ue,{style:{color:"#34d399",width:14,height:14}}),e.jsx(f,{size:"1",style:{color:"#cbd5e1",fontFamily:"monospace"},children:v}),e.jsxs(O,{size:"1",variant:"ghost",style:{color:"#94a3b8",cursor:"pointer",padding:"0 4px",height:20},onClick:i,children:[d?e.jsx(re,{style:{color:"#34d399"}}):e.jsx(he,{}),e.jsx(f,{size:"1",style:{fontSize:10},children:d?"Copied":"Copy"})]})]})]}),e.jsxs(O,{size:"1",variant:"soft",color:"gray",style:{cursor:"pointer"},onClick:()=>{const o=document.createElement("a");o.href=s,o.download=`${h||"officer"}-${l||"photo"}.jpg`,o.target="_blank",o.click()},children:[e.jsx(rt,{}),e.jsx(f,{size:"1",children:"Download"})]})]})})]})]})});Oe.displayName="PhotoTelemetryLightbox";const dt=A.memo(({selectedDate:t,updateMap:c})=>{const[d,w]=u.useState([]),[s,C]=u.useState([]),[h,a]=u.useState(!0),[y,j]=u.useState(!1),[l,v]=u.useState(null),[i,o]=u.useState(""),[p,r]=u.useState("all"),[x,b]=u.useState(()=>localStorage.getItem("guardian_map_tile_id")||"voyager"),[_,F]=u.useState({geofences:!0,waypoints:!0,trajectories:!0}),[S,I]=u.useState(!0),[z,E]=u.useState(!1),[M,g]=u.useState(null),[T,$]=u.useState(null),[G,B]=u.useState(null),[Y,Ae]=u.useState(0),[Ne,xe]=u.useState(null),[ne,pt]=u.useState(!0),[Ue,be]=u.useState(le),ie=u.useRef(null);u.useRef(null);const Ge=u.useCallback(k=>{b(k),localStorage.setItem("guardian_map_tile_id",k)},[]),De=u.useCallback(k=>{F(R=>({...R,[k]:!R[k]}))},[]),H=u.useCallback(async(k=!1)=>{if(t){k?j(!0):a(!0);try{const R=route("getUserLocationsForDate",{date:t.split("T")[0],_t:Date.now()}),D=await fetch(R);if(!D.ok)throw new Error(`HTTP ${D.status}: Failed to fetch user locations`);const W=await D.json(),K=Array.isArray(W.locations)?W.locations:[],q=Array.isArray(W.attendance_type_configs)?W.attendance_type_configs:[];w(K),C(q),v(new Date),be(le)}catch(R){console.error("Failed to load team locations:",R)}finally{a(!1),j(!1)}}},[t]);u.useEffect(()=>{H(!1)},[t,c,H]),u.useEffect(()=>{if(!ne)return;const k=setInterval(()=>{be(R=>R<=1?(H(!0),le):R-1)},1e3);return()=>clearInterval(k)},[ne,H]);const me=u.useMemo(()=>{const k=d.length;let R=0,D=0;return d.forEach(W=>{W.status==="active"?R++:D++}),{total:k,checkedIn:R,active:R,completed:D}},[d]),X=u.useMemo(()=>d.filter(k=>{var R,D,W,K;if(p==="active"&&k.status!=="active"||p==="completed"&&k.status==="active")return!1;if(i){const q=i.toLowerCase(),qe=(R=k.name)==null?void 0:R.toLowerCase().includes(q),Ye=(D=k.employee_id)==null?void 0:D.toLowerCase().includes(q),Ve=(W=k.designation)==null?void 0:W.toLowerCase().includes(q),Qe=(K=k.department)==null?void 0:K.toLowerCase().includes(q);if(!qe&&!Ye&&!Ve&&!Qe)return!1}return!0}),[d,p,i]),We=u.useMemo(()=>l?l.toLocaleTimeString("en-US",{hour:"2-digit",minute:"2-digit",second:"2-digit",hour12:!0}):null,[l]),ye=u.useMemo(()=>{if(!t)return"Invalid Date";try{return new Date(t).toLocaleString("en-US",{weekday:"long",year:"numeric",month:"long",day:"numeric"})}catch{return t}},[t]),je=u.useCallback(k=>{g(k.user_id);const R=k.punchin_location||k.punchout_location||k.location;R&&R.lat&&R.lng&&xe([parseFloat(R.lat),parseFloat(R.lng)])},[]),Be=u.useCallback(k=>{xe(k)},[]),Ze=u.useCallback(()=>{Ae(k=>k+1)},[]),He=u.useCallback(()=>{ie.current&&(document.fullscreenElement?(document.exitFullscreen(),E(!1)):(ie.current.requestFullscreen().catch(k=>{console.warn("Fullscreen error:",k)}),E(!0)))},[]);return u.useEffect(()=>{const k=()=>{E(!!document.fullscreenElement)};return document.addEventListener("fullscreenchange",k),()=>document.removeEventListener("fullscreenchange",k)},[]),e.jsxs(m,{children:[e.jsxs(Xe,{mb:"4",children:[e.jsx(m,{p:"4",style:{borderBottom:"1px solid var(--gray-a4)"},children:e.jsxs(n,{justify:"between",align:"center",gap:"3",wrap:"wrap",children:[e.jsxs(n,{align:"center",gap:"3",children:[e.jsx(m,{style:{padding:10,borderRadius:"var(--radius-3)",background:"linear-gradient(135deg, var(--blue-a3), var(--blue-a4))",border:"1px solid var(--blue-a6)",width:44,height:44,flexShrink:0,display:"flex",alignItems:"center",justifyContent:"center",boxShadow:"0 2px 8px rgba(0,0,0,0.06)"},children:e.jsx(ce,{style:{color:"var(--blue-9)",width:22,height:22}})}),e.jsxs(m,{children:[e.jsx(_e,{size:"4",style:{letterSpacing:"-0.02em"},children:"Team Locations & Live GIS Command Center"}),e.jsx(f,{size:"2",color:"gray",children:ye})]})]}),e.jsxs(O,{variant:"surface",size:"1",color:"blue",onClick:()=>H(!1),disabled:h||y,style:{cursor:"pointer"},children:[e.jsx(de,{className:h||y?"animate-spin":""}),"Refresh Live Feed"]})]})}),e.jsx(Le,{stats:me,lastUpdateText:We,isPolling:ne,secondsLeft:Ue}),e.jsx(m,{p:"4",children:h?e.jsx(n,{align:"center",justify:"center",style:{height:"72vh",border:"1px solid var(--gray-a4)",borderRadius:"var(--radius-3)",background:"var(--gray-a2)"},children:e.jsxs(n,{direction:"column",align:"center",gap:"3",children:[e.jsx(Je,{size:"3"}),e.jsx(f,{size:"2",weight:"medium",color:"gray",children:"Loading team coordinates & GIS boundaries..."})]})}):d.length===0?e.jsxs(n,{direction:"column",align:"center",justify:"center",gap:"3",p:"6",style:{height:"72vh",border:"1px solid var(--gray-a4)",borderRadius:"var(--radius-3)",background:"var(--gray-a2)"},children:[e.jsx(ce,{style:{width:64,height:64,color:"var(--gray-7)"}}),e.jsx(_e,{size:"4",children:"No Team Location Records Found"}),e.jsxs(f,{size:"2",color:"gray",align:"center",style:{maxWidth:420},children:["No check-in or patrol coordinates recorded for ",ye,". Ensure team members have logged attendance via mobile GPS or check a different date."]}),e.jsxs(O,{variant:"outline",onClick:()=>H(!1),children:[e.jsx(de,{})," Refresh Data"]})]}):e.jsxs(m,{ref:ie,style:{position:"relative",height:z?"100vh":"72vh",borderRadius:z?0:"var(--radius-3)",overflow:"hidden",border:z?"none":"1px solid var(--gray-a5)",boxShadow:"0 8px 30px rgba(0,0,0,0.12)"},children:[e.jsx(Ee,{searchQuery:i,onSearchChange:o,statusFilter:p,onStatusFilterChange:r,stats:me,currentTileId:x,onTileChange:Ge,layerVisibility:_,onToggleLayer:De,onFitBounds:Ze,onRefresh:()=>H(!0),isRefreshing:y,isDrawerOpen:S,onToggleDrawer:()=>I(k=>!k),isFullscreen:z,onToggleFullscreen:He}),e.jsxs(Re,{currentTileId:x,users:X,attendanceTypeConfigs:s,fitBoundsTrigger:Y,flyToCoords:Ne,children:[e.jsx(Me,{attendanceTypeConfigs:s,users:X,layerVisibility:_}),e.jsx(Te,{users:X,selectedUserId:M,onSelectOfficer:je,onOpenTelemetry:$,onOpenPhoto:B,layerVisibility:_})]}),e.jsx(Pe,{isOpen:S,onClose:()=>I(!1),users:X,selectedUserId:M,onSelectOfficer:je,onOpenTelemetry:$,onOpenPhoto:B})]})})]}),T&&e.jsx($e,{officer:T,selectedDate:t,onClose:()=>$(null),onOpenPhoto:B,onFocusMap:Be}),G&&e.jsx(Oe,{photoData:G,onClose:()=>B(null)})]})});dt.displayName="UserLocationsCard";export{dt as U};
