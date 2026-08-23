import{j as e,a as m,p as a,b as _,e as Q,u as Ie,c as ie,h as B,d as O,_ as _e,$ as ze,a0 as Fe,al as Se,a1 as ne,t as Le,s as Ke}from"./vendor-radix-DY8pb31i.js";import{R as G,a as p}from"./vendor-inertia-BheeDqvO.js";import"./logRange-CUdWC1SU.js";import"./useObjectionsListState-FGnx5z52.js";import{P as er}from"./ObjectionsStatsSection-CLP5dmif.js";import{m as K,c as ee,d as Re,M as Ee,g as re,G as ue,_ as ae,L as rr,s as ce,ad as de,av as ye,R as ge,aw as tr,ax as fe,ab as or,b as nr,ah as xe,$ as me,p as ir}from"./react-icons.esm-BoLhkdvp.js";import{L as N}from"./leaflet-GjjsV4zE.js";import{d as be,M as ar,T as sr}from"./TileLayer-DAqEQq-9.js";import"./DepartmentForm-N7AyyY4k.js";import"./ErrorBoundary-Dl4rVtSl.js";import"./MonthlyCalendarTab-DnnRbtUa.js";import"./index.esm-MmCp14hd.js";import"./firebase-config-AUwd4BOu.js";import"./vendor-utils-Bd_1ICpc.js";const se={voyager:{id:"voyager",name:"Voyager (Crisp Light)",icon:"Compass",url:"https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png",subdomains:"abcd",maxZoom:20,attribution:'&copy; <a href="https://carto.com/">CARTO</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'},darkMatter:{id:"darkMatter",name:"Dark Matter (Midnight)",icon:"Moon",url:"https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png",subdomains:"abcd",maxZoom:20,attribution:'&copy; <a href="https://carto.com/">CARTO</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'},positron:{id:"positron",name:"Positron (Minimal Light)",icon:"Sun",url:"https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png",subdomains:"abcd",maxZoom:20,attribution:'&copy; <a href="https://carto.com/">CARTO</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'},satellite:{id:"satellite",name:"Satellite (Aerial HD)",icon:"Globe",url:"https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",subdomains:"",maxZoom:19,attribution:"Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community"},osm:{id:"osm",name:"OpenStreetMap Standard",icon:"Map",url:"https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",subdomains:"abc",maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'}},lr=[23.8103,90.4125],cr=12,dr=7,pr=19,pe=15,W={active:"#10b981",completed:"#3b82f6",punchin:"#10b981",punchout:"#ef4444"},hr=`
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
`,Te=G.memo(({stats:r,lastUpdateText:i,isPolling:s,secondsLeft:y})=>{const g=(r==null?void 0:r.total)||0,z=(r==null?void 0:r.checkedIn)??(r==null?void 0:r.active)??0,h=(r==null?void 0:r.completed)||0,c=g>0?Math.round(z/g*100):0;return e.jsx(m,{p:"3",style:{background:"linear-gradient(135deg, var(--gray-a2), var(--gray-a3))",borderBottom:"1px solid var(--gray-a4)"},children:e.jsxs(a,{justify:"between",align:"center",gap:"3",wrap:"wrap",children:[e.jsxs(a,{align:"center",gap:"3",wrap:"wrap",children:[e.jsxs(a,{align:"center",gap:"2",px:"3",py:"2",style:{borderRadius:"var(--radius-3)",background:"var(--color-panel-solid, #ffffff)",border:"1px solid var(--gray-a4)",boxShadow:"0 1px 3px rgba(0,0,0,0.05)"},children:[e.jsx(a,{align:"center",justify:"center",style:{width:28,height:28,borderRadius:"50%",background:"var(--blue-a3)",color:"var(--blue-9)"},children:e.jsx(K,{style:{width:16,height:16}})}),e.jsxs(m,{children:[e.jsxs(a,{align:"baseline",gap:"1",children:[e.jsx(_,{size:"4",weight:"bold",style:{color:"var(--gray-12)"},children:g}),e.jsx(_,{size:"1",color:"gray",children:"Officers"})]}),e.jsx(_,{size:"1",color:"gray",style:{fontSize:10,display:"block",marginTop:-2},children:"Total Tracked"})]})]}),e.jsxs(a,{align:"center",gap:"2",px:"3",py:"2",style:{borderRadius:"var(--radius-3)",background:"var(--color-panel-solid, #ffffff)",border:"1px solid var(--green-a5)",boxShadow:"0 1px 3px rgba(0,0,0,0.05)"},children:[e.jsxs(a,{align:"center",justify:"center",style:{width:28,height:28,borderRadius:"50%",background:"var(--green-a3)",color:"var(--green-9)",position:"relative"},children:[e.jsx(ee,{style:{width:16,height:16}}),z>0&&e.jsx("span",{style:{position:"absolute",top:1,right:1,width:8,height:8,borderRadius:"50%",background:W.active,border:"1.5px solid white"}})]}),e.jsxs(m,{children:[e.jsxs(a,{align:"baseline",gap:"1",children:[e.jsx(_,{size:"4",weight:"bold",style:{color:"var(--green-11)"},children:z}),e.jsxs(Q,{size:"1",color:"green",variant:"soft",radius:"full",children:[c,"%"]})]}),e.jsx(_,{size:"1",color:"gray",style:{fontSize:10,display:"block",marginTop:-2},children:"Active On-Duty"})]})]}),e.jsxs(a,{align:"center",gap:"2",px:"3",py:"2",style:{borderRadius:"var(--radius-3)",background:"var(--color-panel-solid, #ffffff)",border:"1px solid var(--gray-a4)",boxShadow:"0 1px 3px rgba(0,0,0,0.05)"},children:[e.jsx(a,{align:"center",justify:"center",style:{width:28,height:28,borderRadius:"50%",background:"var(--blue-a3)",color:"var(--blue-9)"},children:e.jsx(Re,{style:{width:16,height:16}})}),e.jsxs(m,{children:[e.jsxs(a,{align:"baseline",gap:"1",children:[e.jsx(_,{size:"4",weight:"bold",style:{color:"var(--blue-11)"},children:h}),e.jsx(_,{size:"1",color:"gray",children:"Completed"})]}),e.jsx(_,{size:"1",color:"gray",style:{fontSize:10,display:"block",marginTop:-2},children:"Finished Shifts"})]})]})]}),e.jsxs(a,{align:"center",gap:"2",children:[e.jsxs(a,{align:"center",gap:"2",px:"2",py:"1",style:{background:"var(--gray-a3)",borderRadius:"var(--radius-2)",border:"1px solid var(--gray-a4)"},children:[e.jsx(a,{align:"center",justify:"center",style:{width:8,height:8,borderRadius:"50%",background:s?W.active:"var(--gray-8)",boxShadow:s?"0 0 8px #10b981":"none"}}),e.jsx(_,{size:"1",color:"gray",children:s?`Live Sync (${y}s)`:"Polling Paused"})]}),i&&e.jsxs(_,{size:"1",color:"gray",style:{fontSize:11},children:["Updated: ",i]})]})]})})});Te.displayName="MapStatsRibbon";const $e=G.memo(({searchQuery:r,onSearchChange:i,statusFilter:s,onStatusFilterChange:y,stats:g,currentTileId:z,onTileChange:h,layerVisibility:c,onToggleLayer:b,onFitBounds:d,onRefresh:o,isRefreshing:w,isDrawerOpen:n,onToggleDrawer:l,isFullscreen:f,onToggleFullscreen:t})=>{var v;return e.jsx(m,{style:{position:"absolute",top:14,left:14,right:14,zIndex:1e3,pointerEvents:"none"},children:e.jsxs(a,{gap:"2",align:"center",justify:"between",wrap:"wrap",style:{pointerEvents:"auto"},children:[e.jsxs(a,{align:"center",gap:"2",wrap:"wrap",p:"2",style:{background:"var(--color-surface)",backdropFilter:"blur(16px)",WebkitBackdropFilter:"blur(16px)",borderRadius:"var(--radius-4)",border:"1px solid var(--gray-a5)",boxShadow:"var(--shadow-4, 0 8px 30px rgba(0, 0, 0, 0.12))"},children:[e.jsx(m,{style:{width:190},children:e.jsxs(Ie,{size:"1",variant:"surface",placeholder:"Search officer / ID...",value:r,onChange:x=>i(x.target.value),children:[e.jsx(ie,{children:e.jsx(Ee,{style:{color:"var(--gray-9)"}})}),r&&e.jsx(ie,{children:e.jsx(B,{size:"1",variant:"ghost",color:"gray",style:{cursor:"pointer"},onClick:()=>i(""),children:e.jsx(re,{})})})]})}),e.jsxs(a,{align:"center",gap:"1",children:[e.jsxs(O,{size:"1",variant:s==="all"?"solid":"soft",color:"gray",onClick:()=>y("all"),style:{cursor:"pointer",fontWeight:600},children:["All (",g.total,")"]}),e.jsxs(O,{size:"1",variant:s==="active"?"solid":"soft",color:"green",onClick:()=>y("active"),style:{cursor:"pointer",fontWeight:600},children:["🟢 Active (",g.active,")"]}),e.jsxs(O,{size:"1",variant:s==="completed"?"solid":"soft",color:"blue",onClick:()=>y("completed"),style:{cursor:"pointer",fontWeight:600},children:["✅ Done (",g.completed,")"]})]})]}),e.jsxs(a,{align:"center",gap:"2",p:"2",style:{background:"var(--color-surface)",backdropFilter:"blur(16px)",WebkitBackdropFilter:"blur(16px)",borderRadius:"var(--radius-4)",border:"1px solid var(--gray-a5)",boxShadow:"var(--shadow-4, 0 8px 30px rgba(0, 0, 0, 0.12))"},children:[e.jsxs(_e,{children:[e.jsx(ze,{children:e.jsxs(O,{size:"1",variant:"soft",color:"gray",style:{cursor:"pointer",fontWeight:600},children:[e.jsx(ue,{}),((v=se[z])==null?void 0:v.name)||"Basemap"]})}),e.jsxs(Fe,{variant:"solid",size:"1",children:[e.jsx(Se,{children:"Select Map Tile"}),Object.values(se).map(x=>e.jsxs(ne,{onClick:()=>h(x.id),style:{cursor:"pointer",display:"flex",alignItems:"center",justifyContent:"between"},children:[e.jsx("span",{children:x.name}),z===x.id&&e.jsx(ae,{style:{marginLeft:8}})]},x.id))]})]}),e.jsxs(_e,{children:[e.jsx(ze,{children:e.jsxs(O,{size:"1",variant:"soft",color:"gray",style:{cursor:"pointer",fontWeight:600},children:[e.jsx(rr,{}),"Layers"]})}),e.jsxs(Fe,{variant:"solid",size:"1",children:[e.jsx(Se,{children:"Toggle Overlays"}),e.jsx(ne,{onClick:()=>b("geofences"),style:{cursor:"pointer"},children:e.jsxs(a,{align:"center",gap:"2",children:[c.geofences?e.jsx(ce,{style:{color:"var(--purple-9)"}}):e.jsx(de,{}),e.jsx("span",{children:"Geofence Zones"})]})}),e.jsx(ne,{onClick:()=>b("waypoints"),style:{cursor:"pointer"},children:e.jsxs(a,{align:"center",gap:"2",children:[c.waypoints?e.jsx(ce,{style:{color:"var(--cyan-9)"}}):e.jsx(de,{}),e.jsx("span",{children:"Route Waypoints"})]})}),e.jsx(ne,{onClick:()=>b("trajectories"),style:{cursor:"pointer"},children:e.jsxs(a,{align:"center",gap:"2",children:[c.trajectories?e.jsx(ce,{style:{color:"var(--blue-9)"}}):e.jsx(de,{}),e.jsx("span",{children:"Patrol Trajectories"})]})})]})]}),e.jsxs(O,{size:"1",variant:"soft",color:"gray",onClick:d,style:{cursor:"pointer"},title:"Fit all markers in view",children:[e.jsx(ye,{}),"Fit All"]}),e.jsx(B,{size:"1",variant:"soft",color:"blue",onClick:o,disabled:w,style:{cursor:"pointer"},title:"Refresh live coordinates",children:e.jsx(ge,{className:w?"animate-spin":""})}),e.jsxs(O,{size:"1",variant:n?"solid":"soft",color:n?"blue":"gray",onClick:l,style:{cursor:"pointer",fontWeight:600},children:[e.jsx(K,{}),"Roster (",g.total,")"]}),e.jsx(B,{size:"1",variant:"soft",color:"gray",onClick:t,style:{cursor:"pointer"},title:f?"Exit Fullscreen":"Enter Fullscreen",children:f?e.jsx(tr,{}):e.jsx(fe,{})})]})]})})});$e.displayName="MapHudControls";L.Control.Fullscreen=L.Control.extend({options:{position:"topleft",title:{false:"View Fullscreen",true:"Exit Fullscreen"}},onAdd:function(r){var i=L.DomUtil.create("div","leaflet-control-fullscreen leaflet-bar leaflet-control");return this.link=L.DomUtil.create("a","leaflet-control-fullscreen-button leaflet-bar-part",i),this.link.href="#",this._map=r,this._map.on("fullscreenchange",this._toggleTitle,this),this._toggleTitle(),L.DomEvent.on(this.link,"click",this._click,this),i},_click:function(r){L.DomEvent.stopPropagation(r),L.DomEvent.preventDefault(r),this._map.toggleFullscreen(this.options)},_toggleTitle:function(){this.link.title=this.options.title[this._map.isFullscreen()]}});L.Map.include({isFullscreen:function(){return this._isFullscreen||!1},toggleFullscreen:function(r){var i=this.getContainer();this.isFullscreen()?r&&r.pseudoFullscreen?this._disablePseudoFullscreen(i):document.exitFullscreen?document.exitFullscreen():document.mozCancelFullScreen?document.mozCancelFullScreen():document.webkitCancelFullScreen?document.webkitCancelFullScreen():document.msExitFullscreen?document.msExitFullscreen():this._disablePseudoFullscreen(i):r&&r.pseudoFullscreen?this._enablePseudoFullscreen(i):i.requestFullscreen?i.requestFullscreen():i.mozRequestFullScreen?i.mozRequestFullScreen():i.webkitRequestFullscreen?i.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT):i.msRequestFullscreen?i.msRequestFullscreen():this._enablePseudoFullscreen(i)},_enablePseudoFullscreen:function(r){L.DomUtil.addClass(r,"leaflet-pseudo-fullscreen"),this._setFullscreen(!0),this.fire("fullscreenchange")},_disablePseudoFullscreen:function(r){L.DomUtil.removeClass(r,"leaflet-pseudo-fullscreen"),this._setFullscreen(!1),this.fire("fullscreenchange")},_setFullscreen:function(r){this._isFullscreen=r;var i=this.getContainer();r?L.DomUtil.addClass(i,"leaflet-fullscreen-on"):L.DomUtil.removeClass(i,"leaflet-fullscreen-on"),this.invalidateSize()},_onFullscreenChange:function(r){var i=document.fullscreenElement||document.mozFullScreenElement||document.webkitFullscreenElement||document.msFullscreenElement;i===this.getContainer()&&!this._isFullscreen?(this._setFullscreen(!0),this.fire("fullscreenchange")):i!==this.getContainer()&&this._isFullscreen&&(this._setFullscreen(!1),this.fire("fullscreenchange"))}});L.Map.mergeOptions({fullscreenControl:!1});L.Map.addInitHook(function(){this.options.fullscreenControl&&(this.fullscreenControl=new L.Control.Fullscreen(this.options.fullscreenControl),this.addControl(this.fullscreenControl));var r;if("onfullscreenchange"in document?r="fullscreenchange":"onmozfullscreenchange"in document?r="mozfullscreenchange":"onwebkitfullscreenchange"in document?r="webkitfullscreenchange":"onmsfullscreenchange"in document&&(r="MSFullscreenChange"),r){var i=L.bind(this._onFullscreenChange,this);this.whenReady(function(){L.DomEvent.on(document,r,i)}),this.on("unload",function(){L.DomEvent.off(document,r,i)})}});L.control.fullscreen=function(r){return new L.Control.Fullscreen(r)};const Pe=G.memo(({fitBoundsTrigger:r,users:i,flyToCoords:s,attendanceTypeConfigs:y})=>{const g=be();return p.useEffect(()=>{if(!g||r===0)return;const z=N.latLngBounds([]);(i||[]).forEach(h=>{const c=h.punchin_location||h.location,b=h.punchout_location;c&&c.lat&&c.lng&&z.extend([parseFloat(c.lat),parseFloat(c.lng)]),b&&b.lat&&b.lng&&z.extend([parseFloat(b.lat),parseFloat(b.lng)])}),(y||[]).forEach(h=>{var c,b;(c=h.config)!=null&&c.polygon&&h.config.polygon.forEach(d=>{d.lat&&d.lng&&z.extend([parseFloat(d.lat),parseFloat(d.lng)])}),(b=h.config)!=null&&b.waypoints&&h.config.waypoints.forEach(d=>{d.lat&&d.lng&&z.extend([parseFloat(d.lat),parseFloat(d.lng)])})}),z.isValid()&&g.fitBounds(z,{padding:[60,60],maxZoom:15,animate:!0,duration:.8})},[g,r,i,y]),p.useEffect(()=>{!g||!s||g.flyTo(s,16,{animate:!0,duration:1.2})},[g,s]),null});Pe.displayName="MapController";const Me=G.memo(({currentTileId:r="voyager",users:i=[],attendanceTypeConfigs:s=[],fitBoundsTrigger:y=0,flyToCoords:g=null,children:z})=>{const h=se[r]||se.voyager;return p.useEffect(()=>{const c="team-map-injected-styles";if(!document.getElementById(c)){const b=document.createElement("style");b.id=c,b.innerHTML=hr,document.head.appendChild(b)}},[]),e.jsx("div",{style:{position:"relative",width:"100%",height:"100%"},children:e.jsxs(ar,{center:lr,zoom:cr,minZoom:dr,maxZoom:pr,style:{width:"100%",height:"100%",background:"#0f172a"},scrollWheelZoom:!0,doubleClickZoom:!0,dragging:!0,touchZoom:!0,zoomControl:!1,attributionControl:!1,children:[e.jsx(sr,{url:h.url,subdomains:h.subdomains,maxZoom:h.maxZoom,attribution:h.attribution},h.id),e.jsx(Pe,{fitBoundsTrigger:y,users:i,flyToCoords:g,attendanceTypeConfigs:s}),z]})})});Me.displayName="MapContainerView";const he=r=>{if(!r)return null;if(Array.isArray(r)&&r.length>=2){const i=parseFloat(r[0]),s=parseFloat(r[1]);if(!isNaN(i)&&!isNaN(s))return{lat:i,lng:s}}if(typeof r=="object"){const i=r.lat??r.latitude,s=r.lng??r.longitude;if(i!==void 0&&s!==void 0){const y=parseFloat(i),g=parseFloat(s);if(!isNaN(y)&&!isNaN(g))return{lat:y,lng:g}}}return null},Ae=G.memo(({attendanceTypeConfigs:r=[],users:i=[],layerVisibility:s={geofences:!0,waypoints:!0,trajectories:!0}})=>{const y=be(),g=p.useRef([]);return p.useEffect(()=>{if(!y||(g.current.forEach(h=>{try{y.removeLayer(h)}catch{}}),g.current=[],!r||r.length===0))return;const z=["#3b82f6","#10b981","#f59e0b","#8b5cf6","#ec4899","#06b6d4","#14b8a6","#f97316"];return r.forEach((h,c)=>{var t,v,x,S;const{base_slug:b,slug:d,config:o,name:w}=h,n=z[c%z.length];if(!o)return;if((b==="geo_polygon"||(d==null?void 0:d.includes("polygon"))||(d==null?void 0:d.includes("geofence"))||!!((t=o.polygon)!=null&&t.length||(v=o.polygons)!=null&&v.length))&&s.geofences!==!1){const T=o.polygon||[],R=o.polygons||[],I=(C,j)=>{const F=(C||[]).map(he).filter(Boolean);if(F.length<3)return;const u=F.map(U=>[U.lat,U.lng]),E=N.polygon(u,{color:n,fillColor:n,fillOpacity:.16,weight:2.5,opacity:.85,dashArray:"6, 6"}).addTo(y),A=E.getBounds(),P=A.getCenter(),M=i.filter(U=>{const q=he(U.punchin_location||U.punchout_location||U.location);return q?A.contains(N.latLng(q.lat,q.lng)):!1}).length,D=`
                        <div class="geofence-centroid-badge" style="border-color: ${n}88;">
                            <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${n};"></span>
                            <span>${j||w}</span>
                            ${M>0?`<span style="background:${n}; color:white; border-radius:10px; padding:0 6px; font-size:10px;">${M} Officers</span>`:""}
                        </div>
                    `,V=N.marker(P,{icon:N.divIcon({html:D,className:"geofence-label-marker",iconSize:[120,26],iconAnchor:[60,13]}),interactive:!1}).addTo(y);E.bindPopup(`
                        <div style="font-family: inherit; padding: 6px; min-width: 140px; color: var(--gray-12, #1e293b);">
                            <div style="font-weight: 700; color: ${n}; font-size: 13px; margin-bottom: 2px;">
                                🛡️ ${j||w}
                            </div>
                            <div style="font-size: 11px; color: var(--gray-10, #64748b);">Geofence Zone Perimeter</div>
                            <div style="font-size: 11px; margin-top: 4px; font-weight: 600;">
                                Verified Officers: <span style="color:${n};">${M}</span>
                            </div>
                        </div>
                    `),g.current.push(E),g.current.push(V)};T.length>=3&&I(T,w),R.forEach((C,j)=>{const F=C.points||C.coordinates||C;Array.isArray(F)&&F.length>=3&&I(F,C.name||`${w} Zone ${j+1}`)})}if((b==="route_waypoint"||(d==null?void 0:d.includes("route"))||(d==null?void 0:d.includes("waypoint"))||(d==null?void 0:d.includes("patrol"))||!!((x=o.waypoints)!=null&&x.length||(S=o.routes)!=null&&S.length))&&s.waypoints!==!1){const T=o.waypoints||[],R=o.routes||[],I=(j,F,u)=>{const E=(j||[]).map(he).filter(Boolean);if(E.length===0)return;const A=E.map(P=>[P.lat,P.lng]);if(A.length>=2){const P=N.polyline(A,{color:n,weight:3.5,opacity:.75,dashArray:"8, 6"}).addTo(y);g.current.push(P)}E.forEach((P,M)=>{const D=M===0,V=M===E.length-1&&E.length>1,U=D?"#10b981":V?"#ef4444":n;if(u&&u>0){const ve=N.circle([P.lat,P.lng],{radius:u,color:U,fillColor:U,fillOpacity:.08,weight:1.5,dashArray:"4, 4"}).addTo(y);g.current.push(ve)}const q=`
                            <div style="
                                width: 26px;
                                height: 26px;
                                border-radius: 50%;
                                background: ${U};
                                border: 2px solid var(--color-surface, #ffffff);
                                box-shadow: 0 3px 8px rgba(0,0,0,0.35);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                color: white;
                                font-weight: 800;
                                font-size: 11px;
                            ">
                                ${E.length===1?"📍":D?"S":V?"E":M+1}
                            </div>
                        `,J=N.marker([P.lat,P.lng],{icon:N.divIcon({html:q,className:"waypoint-marker",iconSize:[26,26],iconAnchor:[13,13]})}).addTo(y);J.bindPopup(`
                            <div style="font-family: inherit; padding: 4px; color: var(--gray-12, #1e293b);">
                                <strong style="color: ${n};">${F||w}</strong><br>
                                <span style="font-size: 11px; color: var(--gray-10, #64748b);">
                                    ${E.length===1?"🎯 Patrol Checkpoint":D?"🚀 Route Start Point":V?"🏁 Route End Point":`Waypoint #${M+1}`}
                                </span>
                                ${u?`<div style="font-size: 10px; color: var(--gray-9); margin-top: 2px;">Tolerance: ${u}m</div>`:""}
                            </div>
                        `),g.current.push(J)})},C=o.tolerance||150;T.length>0&&I(T,w,C),R.forEach((j,F)=>{const u=j.waypoints||j.points||j.coords;Array.isArray(u)&&u.length>0&&I(u,j.name||`${w} Route ${F+1}`,j.tolerance||C)})}}),()=>{g.current.forEach(h=>{try{y.removeLayer(h)}catch{}}),g.current=[]}},[y,r,i,s]),null});Ae.displayName="MapGeofenceLayers";const Ne=G.memo(({users:r=[],selectedUserId:i,onSelectOfficer:s,onOpenTelemetry:y,onOpenPhoto:g,layerVisibility:z={trajectories:!0}})=>{const h=be(),c=p.useRef([]),b=p.useRef([]),d=p.useCallback(n=>{if(!n)return null;if(typeof n=="object"&&n.lat&&n.lng){const l=parseFloat(n.lat),f=parseFloat(n.lng);if(!isNaN(l)&&!isNaN(f))return{lat:l,lng:f}}if(typeof n=="string")try{const l=JSON.parse(n);if(l.lat&&l.lng){const f=parseFloat(l.lat),t=parseFloat(l.lng);if(!isNaN(f)&&!isNaN(t))return{lat:f,lng:t}}}catch{const f=n.split(",");if(f.length>=2){const t=parseFloat(f[0].trim()),v=parseFloat(f[1].trim());if(!isNaN(t)&&!isNaN(v))return{lat:t,lng:v}}}return null},[]),o=p.useCallback((n,l="active",f=!1)=>{var F,u;const t=n.status==="active"||l==="punchin",v=l==="punchout",x=n.profile_image_url,S=((u=(F=n.name)==null?void 0:F.charAt(0))==null?void 0:u.toUpperCase())||"?",T=t?'<div class="living-marker-radar-ring"></div>':"",R=`living-marker-core ${t?"is-active":v?"is-punchout":"is-completed"}`,I=t?W.active:v?W.punchout:W.completed,C=t?"▶":v?"◼":"✓",j=`
            <div class="living-marker-wrapper" style="${f?"transform: scale(1.25); z-index: 9999;":""}">
                ${T}
                <div class="${R}" style="${f?"border-color: #38bdf8; box-shadow: 0 0 16px #38bdf8;":""}">
                    ${x?`<img src="${x}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; this.parentElement.innerText='${S}';" />`:S}
                </div>
                <div class="living-marker-badge" style="background: ${I};">
                    ${C}
                </div>
            </div>
        `;return N.divIcon({html:j,className:"custom-living-marker",iconSize:[44,44],iconAnchor:[22,22],popupAnchor:[0,-22]})},[]),w=p.useCallback((n,l,f="current")=>{var C,j;const t=n.status==="active",v=(l==null?void 0:l.punchin_time)||n.punchin_time||"--",x=(l==null?void 0:l.punchout_time)||n.punchout_time,S=(l==null?void 0:l.punchin_photo_url)||n.punchin_photo_url,T=(l==null?void 0:l.punchout_photo_url)||n.punchout_photo_url,R=f==="punchout"&&T||S,I=R?`
            <div style="margin: 8px 0; border-radius: 6px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15); max-height: 90px; cursor: pointer; position: relative;"
                 onclick="window.__openMapPhoto && window.__openMapPhoto('${R}', '${n.name.replace(/'/g,"\\'")}', '${v}', '${f}')">
                <img src="${R}" style="width: 100%; height: 85px; object-fit: cover;" alt="Selfie" />
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
                        border: 2px solid ${t?W.active:"var(--gray-8, #94a3b8)"};
                        background: var(--gray-a4, #e2e8f0);
                        color: var(--gray-12, #1e293b);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-weight: bold;
                        font-size: 12px;
                        flex-shrink: 0;
                    ">
                        ${n.profile_image_url?`<img src="${n.profile_image_url}" style="width:100%; height:100%; object-fit:cover;" />`:((j=(C=n.name)==null?void 0:C.charAt(0))==null?void 0:j.toUpperCase())||"?"}
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
                        background: ${t?"var(--green-a3, rgba(16, 185, 129, 0.15))":"var(--blue-a3, rgba(59, 130, 246, 0.15))"};
                        color: ${t?"var(--green-11, #059669)":"var(--blue-11, #2563eb)"};
                        border: 1px solid ${t?"var(--green-a5, rgba(16, 185, 129, 0.4))":"var(--blue-a5, rgba(59, 130, 246, 0.4))"};
                    ">
                        ${t?"🟢 ACTIVE":"✅ DONE"}
                    </div>
                </div>

                <!-- Timestamps -->
                <div style="background: var(--gray-a3, rgba(0, 0, 0, 0.04)); border-radius: 6px; padding: 6px; margin-bottom: 6px; font-size: 11px; border: 1px solid var(--gray-a4);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 3px;">
                        <span style="color: var(--gray-10, #64748b);">Check In:</span>
                        <span style="font-weight: 600; color: var(--green-11, #059669);">${v}</span>
                    </div>
                    ${x?`
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="color: var(--gray-10, #64748b);">Check Out:</span>
                        <span style="font-weight: 600; color: var(--red-11, #dc2626);">${x}</span>
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
        `},[]);return p.useEffect(()=>(window.__inspectOfficer=n=>{const l=r.find(f=>f.user_id===n);l&&y&&y(l)},window.__openMapPhoto=(n,l,f,t)=>{g&&g({url:n,officerName:l,timestamp:f,type:t})},()=>{delete window.__inspectOfficer,delete window.__openMapPhoto}),[r,y,g]),p.useEffect(()=>{if(!h||(c.current.forEach(t=>{try{h.removeLayer(t)}catch{}}),c.current=[],b.current.forEach(t=>{try{h.removeLayer(t)}catch{}}),b.current=[],!r||r.length===0))return;const n=[],l=15e-5,f=t=>{let v=t.lat,x=t.lng;const S=n.filter(T=>Math.abs(T.lat-v)<l&&Math.abs(T.lng-x)<l).length;if(S>0){const T=S*1.25,R=18e-5*Math.sqrt(S);v+=Math.cos(T)*R,x+=Math.sin(T)*R}return n.push({lat:v,lng:x}),{lat:v,lng:x}};return r.forEach(t=>{const v=t.cycles&&t.cycles.length>0?t.cycles:null,x=i===t.user_id;if(v)v.forEach((S,T)=>{const R=d(S.punchin_location),I=d(S.punchout_location);if(R&&I&&S.is_complete){const C=f(R),j=f(I),F=N.marker([C.lat,C.lng],{icon:o(t,"punchin",x),zIndexOffset:x?1e3:100}).addTo(h);F.bindPopup(w(t,S,"punchin")),F.on("click",()=>s&&s(t)),c.current.push(F);const u=N.marker([j.lat,j.lng],{icon:o(t,"punchout",x),zIndexOffset:x?1e3:90}).addTo(h);if(u.bindPopup(w(t,S,"punchout")),u.on("click",()=>s&&s(t)),c.current.push(u),z.trajectories){const E=N.polyline([[C.lat,C.lng],[j.lat,j.lng]],{color:"#06b6d4",weight:3.5,opacity:.8,className:"patrol-trajectory-path"}).addTo(h);b.current.push(E)}}else{const C=R||I;if(C){const j=f(C),F=N.marker([j.lat,j.lng],{icon:o(t,t.status,x),zIndexOffset:x?1e3:150}).addTo(h);F.bindPopup(w(t,S,"punchin")),F.on("click",()=>s&&s(t)),c.current.push(F)}}});else{const S=d(t.punchin_location||t.location),T=d(t.punchout_location),R=S||T;if(R){const I=f(R),C=N.marker([I.lat,I.lng],{icon:o(t,t.status,x),zIndexOffset:x?1e3:100}).addTo(h);if(C.bindPopup(w(t,t,t.status)),C.on("click",()=>s&&s(t)),c.current.push(C),S&&T&&t.punchout_time&&z.trajectories){const j=f(T),F=N.polyline([[I.lat,I.lng],[j.lat,j.lng]],{color:"#06b6d4",weight:3.5,opacity:.8,className:"patrol-trajectory-path"}).addTo(h);b.current.push(F)}}}}),()=>{c.current.forEach(t=>{try{h.removeLayer(t)}catch{}}),c.current=[],b.current.forEach(t=>{try{h.removeLayer(t)}catch{}}),b.current=[]}},[h,r,i,z,o,w,d,s]),null});Ne.displayName="MapLivingMarkers";const Oe=G.memo(({isOpen:r,onClose:i,users:s=[],selectedUserId:y,onSelectOfficer:g,onOpenTelemetry:z,onOpenPhoto:h})=>{const[c,b]=p.useState(""),d=p.useMemo(()=>{if(!c)return s;const o=c.toLowerCase();return s.filter(w=>{var n,l,f,t;return((n=w.name)==null?void 0:n.toLowerCase().includes(o))||((l=w.employee_id)==null?void 0:l.toLowerCase().includes(o))||((f=w.designation)==null?void 0:f.toLowerCase().includes(o))||((t=w.department)==null?void 0:t.toLowerCase().includes(o))})},[s,c]);return r?e.jsxs(m,{style:{position:"absolute",top:74,right:14,bottom:14,width:320,maxWidth:"calc(100vw - 28px)",background:"var(--color-panel-solid, var(--color-surface, #ffffff))",backdropFilter:"blur(20px)",WebkitBackdropFilter:"blur(20px)",borderRadius:"var(--radius-4)",border:"1px solid var(--gray-a5)",boxShadow:"var(--shadow-5, 0 20px 40px rgba(0, 0, 0, 0.25))",zIndex:1e3,display:"flex",flexDirection:"column",overflow:"hidden",animation:"slideInRight 0.25s cubic-bezier(0.16, 1, 0.3, 1)"},children:[e.jsxs(m,{p:"3",style:{borderBottom:"1px solid var(--gray-a4)",background:"var(--gray-a2)"},children:[e.jsxs(a,{justify:"between",align:"center",mb:"2",children:[e.jsxs(a,{align:"center",gap:"2",children:[e.jsx(K,{style:{color:"var(--blue-9)",width:16,height:16}}),e.jsx(_,{size:"2",weight:"bold",style:{color:"var(--gray-12)"},children:"On-Duty Team Roster"}),e.jsx(Q,{size:"1",color:"blue",variant:"solid",radius:"full",children:s.length})]}),e.jsx(B,{size:"1",variant:"ghost",color:"gray",style:{cursor:"pointer"},onClick:i,children:e.jsx(re,{})})]}),e.jsxs(Ie,{size:"1",variant:"surface",placeholder:"Filter roster...",value:c,onChange:o=>b(o.target.value),children:[e.jsx(ie,{children:e.jsx(Ee,{style:{color:"var(--gray-9)"}})}),c&&e.jsx(ie,{children:e.jsx(B,{size:"1",variant:"ghost",color:"gray",onClick:()=>b(""),children:e.jsx(re,{})})})]})]}),e.jsx(m,{p:"2",style:{flex:1,overflowY:"auto",display:"flex",flexDirection:"column",gap:6},children:d.length===0?e.jsxs(a,{align:"center",justify:"center",direction:"column",gap:"2",p:"4",style:{height:"100%"},children:[e.jsx(K,{style:{color:"var(--gray-8)",width:28,height:28}}),e.jsx(_,{size:"1",color:"gray",children:"No matching officers found"})]}):d.map(o=>{var t,v;const w=y===o.user_id,n=o.status==="active",l=o.punchin_time||"--",f=o.punchout_time;return o.punchin_photo_url||o.profile_image_url,e.jsxs(m,{p:"2",style:{borderRadius:"var(--radius-3)",background:w?"var(--blue-a3)":"var(--gray-a2)",border:w?"1px solid var(--blue-a7)":"1px solid var(--gray-a4)",transition:"all 0.15s ease",cursor:"pointer"},onClick:()=>g(o),children:[e.jsxs(a,{justify:"between",align:"start",gap:"2",children:[e.jsxs(a,{align:"center",gap:"2",style:{minWidth:0,flex:1},children:[e.jsxs(m,{style:{position:"relative",width:34,height:34,borderRadius:"50%",overflow:"hidden",border:`2px solid ${n?W.active:"var(--gray-7)"}`,background:"var(--gray-a4)",display:"flex",alignItems:"center",justifyContent:"center",color:"white",fontWeight:"bold",fontSize:12,flexShrink:0},children:[o.profile_image_url?e.jsx("img",{src:o.profile_image_url,alt:o.name,style:{width:"100%",height:"100%",objectFit:"cover"}}):((v=(t=o.name)==null?void 0:t.charAt(0))==null?void 0:v.toUpperCase())||"?",e.jsx("span",{style:{position:"absolute",bottom:0,right:0,width:8,height:8,borderRadius:"50%",background:n?W.active:W.completed,border:"1px solid var(--color-surface)"}})]}),e.jsxs(m,{style:{minWidth:0,flex:1},children:[e.jsx(_,{size:"2",weight:"bold",style:{color:"var(--gray-12)",whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis",display:"block"},children:o.name}),e.jsxs(_,{size:"1",color:"gray",style:{whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis",display:"block"},children:[o.designation||"Staff"," ",o.employee_id?`• ${o.employee_id}`:""]})]})]}),e.jsx(B,{size:"1",variant:"soft",color:w?"blue":"gray",onClick:x=>{x.stopPropagation(),g(o)},title:"Fly to marker on map",children:e.jsx(ye,{})})]}),e.jsxs(a,{justify:"between",align:"center",mt:"2",pt:"2",style:{borderTop:"1px solid var(--gray-a4)"},children:[e.jsxs(a,{align:"center",gap:"1",children:[e.jsx(ee,{style:{color:"var(--green-9)",width:12,height:12}}),e.jsxs(_,{size:"1",weight:"medium",style:{color:"var(--green-11)"},children:["In: ",l]}),f&&e.jsxs(_,{size:"1",color:"gray",ml:"1",children:["• Out: ",f]})]}),e.jsxs(a,{align:"center",gap:"1",children:[o.punchin_photo_url&&e.jsx(B,{size:"1",variant:"ghost",color:"blue",onClick:x=>{x.stopPropagation(),h({url:o.punchin_photo_url,title:`Check-In Verification: ${o.name}`,timestamp:l,officerName:o.name,employeeId:o.employee_id,designation:o.designation,location:o.punchin_location})},title:"View Check-In Selfie",children:e.jsx(or,{})}),e.jsxs(O,{size:"1",variant:"surface",color:"gray",onClick:x=>{x.stopPropagation(),z(o)},style:{cursor:"pointer",height:22,fontSize:10,padding:"0 6px"},children:["Telemetry",e.jsx(nr,{})]})]})]})]},o.user_id)})})]}):null});Oe.displayName="MapTeamRosterDrawer";const Ue=G.memo(({officer:r,selectedDate:i,onClose:s,onOpenPhoto:y,onFocusMap:g})=>{var F;const[z,h]=p.useState(null);if(!r)return null;const{name:c,employee_id:b,designation:d,department:o,profile_image_url:w,status:n,cycles:l=[],punchin_time:f,punchout_time:t,punchin_location:v,punchout_location:x,punchin_photo_url:S,punchout_photo_url:T,attendance_type:R}=r,I=n==="active",C=(u,E)=>{u&&(navigator.clipboard.writeText(u),h(E),setTimeout(()=>h(null),2e3))},j=l&&l.length>0?l:[{attendance_id:"default",punchin_time:f,punchout_time:t,punchin_location:v,punchout_location:x,punchin_photo_url:S,punchout_photo_url:T,is_complete:!!t}];return e.jsx(m,{style:{position:"fixed",inset:0,background:"rgba(5, 10, 20, 0.75)",backdropFilter:"blur(8px)",WebkitBackdropFilter:"blur(8px)",zIndex:99990,display:"flex",alignItems:"center",justifyContent:"center",padding:16,animation:"fadeIn 0.2s ease-out"},onClick:s,children:e.jsxs(m,{style:{width:"100%",maxWidth:580,maxHeight:"90vh",background:"var(--color-panel-solid, #1e293b)",borderRadius:"var(--radius-4)",border:"1px solid var(--gray-a6)",boxShadow:"0 25px 60px -15px rgba(0,0,0,0.5)",display:"flex",flexDirection:"column",overflow:"hidden"},onClick:u=>u.stopPropagation(),children:[e.jsx(m,{p:"4",style:{background:"linear-gradient(135deg, var(--gray-a3), var(--gray-a4))",borderBottom:"1px solid var(--gray-a5)"},children:e.jsxs(a,{justify:"between",align:"start",children:[e.jsxs(a,{align:"center",gap:"3",children:[e.jsx(m,{style:{width:52,height:52,borderRadius:"50%",overflow:"hidden",border:`3px solid ${I?W.active:"var(--gray-a7)"}`,background:"var(--gray-a4)",display:"flex",alignItems:"center",justifyContent:"center",color:"white",fontWeight:"bold",fontSize:18,flexShrink:0,boxShadow:I?"0 0 12px rgba(16, 185, 129, 0.4)":"none"},children:w?e.jsx("img",{src:w,alt:c,style:{width:"100%",height:"100%",objectFit:"cover"}}):((F=c==null?void 0:c.charAt(0))==null?void 0:F.toUpperCase())||"?"}),e.jsxs(m,{children:[e.jsxs(a,{align:"center",gap:"2",wrap:"wrap",children:[e.jsx(_,{size:"3",weight:"bold",style:{color:"var(--gray-12)"},children:c||"Officer"}),e.jsx(Q,{size:"1",color:I?"green":"blue",variant:"solid",radius:"full",children:I?"🟢 Active On-Duty":"✅ Shift Completed"})]}),e.jsxs(_,{size:"1",color:"gray",children:[d||"Employee"," ",o?`• ${o}`:"",b?` • ID: ${b}`:""]}),R&&e.jsxs(Q,{size:"1",color:"purple",variant:"soft",mt:"1",children:["Zone: ",R.name||"Standard"]})]})]}),e.jsx(B,{size:"2",variant:"ghost",color:"gray",onClick:s,style:{cursor:"pointer"},children:e.jsx(re,{})})]})}),e.jsxs(m,{p:"4",style:{overflowY:"auto",flex:1,display:"flex",flexDirection:"column",gap:16},children:[e.jsxs(a,{justify:"between",align:"center",children:[e.jsxs(_,{size:"2",weight:"bold",style:{color:"var(--gray-11)"},children:["Attendance & Patrol Telemetry (",j.length," ",j.length===1?"Cycle":"Cycles",")"]}),e.jsxs(_,{size:"1",color:"gray",children:["Date: ",i||"Today"]})]}),j.map((u,E)=>{const A=u.punchin_location,P=u.punchout_location,M=A&&A.lat&&A.lng?`${parseFloat(A.lat).toFixed(5)}, ${parseFloat(A.lng).toFixed(5)}`:null,D=P&&P.lat&&P.lng?`${parseFloat(P.lat).toFixed(5)}, ${parseFloat(P.lng).toFixed(5)}`:null;return e.jsxs(m,{p:"3",style:{background:"var(--gray-a2)",borderRadius:"var(--radius-3)",border:"1px solid var(--gray-a4)"},children:[e.jsxs(a,{justify:"between",align:"center",mb:"3",children:[e.jsxs(Q,{size:"1",color:"gray",variant:"surface",children:["Shift Cycle #",E+1]}),e.jsx(Q,{size:"1",color:u.is_complete?"blue":"green",variant:"soft",children:u.is_complete?"Cycle Finished":"Active Cycle"})]}),e.jsxs(a,{direction:"column",gap:"3",children:[e.jsxs(a,{align:"start",justify:"between",p:"2",style:{background:"var(--green-a2)",borderRadius:"var(--radius-2)",border:"1px solid var(--green-a4)"},children:[e.jsxs(a,{align:"start",gap:"2",style:{flex:1},children:[e.jsx(m,{style:{width:24,height:24,borderRadius:"50%",background:W.punchin,color:"white",display:"flex",alignItems:"center",justifyContent:"center",flexShrink:0},children:e.jsx(ee,{style:{width:14,height:14}})}),e.jsxs(m,{children:[e.jsxs(_,{size:"1",weight:"bold",style:{color:"var(--green-11)"},children:["Check-In: ",u.punchin_time||"--"]}),M?e.jsxs(a,{align:"center",gap:"1",mt:"1",children:[e.jsx(xe,{style:{color:"var(--green-9)",width:12,height:12}}),e.jsx(_,{size:"1",style:{fontSize:11,fontFamily:"monospace",color:"var(--gray-11)"},children:M}),e.jsx(B,{size:"1",variant:"ghost",style:{height:18,width:18},onClick:()=>C(M,`in-${E}`),children:z===`in-${E}`?e.jsx(ae,{}):e.jsx(me,{})})]}):e.jsx(_,{size:"1",color:"gray",style:{fontSize:11},children:"No GPS coordinates"})]})]}),u.punchin_photo_url&&e.jsxs(m,{style:{width:48,height:48,borderRadius:"var(--radius-2)",overflow:"hidden",border:"1px solid var(--green-a6)",cursor:"pointer",position:"relative",flexShrink:0},onClick:()=>y&&y({url:u.punchin_photo_url,officerName:c,designation:d,timestamp:u.punchin_time,location:A,type:"punchin"}),children:[e.jsx("img",{src:u.punchin_photo_url,alt:"Check-in selfie",style:{width:"100%",height:"100%",objectFit:"cover"}}),e.jsx(m,{style:{position:"absolute",bottom:0,insetInline:0,background:"rgba(0,0,0,0.6)",display:"flex",alignItems:"center",justifyContent:"center",padding:1},children:e.jsx(fe,{style:{color:"white",width:10,height:10}})})]})]}),u.punchout_time?e.jsxs(a,{align:"start",justify:"between",p:"2",style:{background:"var(--red-a2)",borderRadius:"var(--radius-2)",border:"1px solid var(--red-a4)"},children:[e.jsxs(a,{align:"start",gap:"2",style:{flex:1},children:[e.jsx(m,{style:{width:24,height:24,borderRadius:"50%",background:W.punchout,color:"white",display:"flex",alignItems:"center",justifyContent:"center",flexShrink:0},children:e.jsx(Re,{style:{width:14,height:14}})}),e.jsxs(m,{children:[e.jsxs(_,{size:"1",weight:"bold",style:{color:"var(--red-11)"},children:["Check-Out: ",u.punchout_time]}),D?e.jsxs(a,{align:"center",gap:"1",mt:"1",children:[e.jsx(xe,{style:{color:"var(--red-9)",width:12,height:12}}),e.jsx(_,{size:"1",style:{fontSize:11,fontFamily:"monospace",color:"var(--gray-11)"},children:D}),e.jsx(B,{size:"1",variant:"ghost",style:{height:18,width:18},onClick:()=>C(D,`out-${E}`),children:z===`out-${E}`?e.jsx(ae,{}):e.jsx(me,{})})]}):e.jsx(_,{size:"1",color:"gray",style:{fontSize:11},children:"No GPS coordinates"})]})]}),u.punchout_photo_url&&e.jsxs(m,{style:{width:48,height:48,borderRadius:"var(--radius-2)",overflow:"hidden",border:"1px solid var(--red-a6)",cursor:"pointer",position:"relative",flexShrink:0},onClick:()=>y&&y({url:u.punchout_photo_url,officerName:c,designation:d,timestamp:u.punchout_time,location:P,type:"punchout"}),children:[e.jsx("img",{src:u.punchout_photo_url,alt:"Check-out selfie",style:{width:"100%",height:"100%",objectFit:"cover"}}),e.jsx(m,{style:{position:"absolute",bottom:0,insetInline:0,background:"rgba(0,0,0,0.6)",display:"flex",alignItems:"center",justifyContent:"center",padding:1},children:e.jsx(fe,{style:{color:"white",width:10,height:10}})})]})]}):e.jsxs(a,{align:"center",gap:"2",p:"2",style:{background:"var(--gray-a3)",borderRadius:"var(--radius-2)",border:"1px dashed var(--gray-a5)"},children:[e.jsx(ee,{style:{color:"var(--amber-9)"}}),e.jsx(_,{size:"1",color:"gray",children:"Officer is currently on active patrol. Check-out not recorded yet."})]})]})]},E)})]}),e.jsx(m,{p:"3",style:{background:"var(--gray-a2)",borderTop:"1px solid var(--gray-a4)"},children:e.jsxs(a,{justify:"between",align:"center",gap:"2",children:[e.jsxs(O,{variant:"surface",color:"blue",size:"2",onClick:()=>{if(s(),g){const u=v||x;u&&u.lat&&u.lng&&g([parseFloat(u.lat),parseFloat(u.lng)])}},children:[e.jsx(ye,{})," Focus on Map"]}),e.jsx(O,{variant:"outline",color:"gray",size:"2",onClick:s,children:"Close"})]})})]})})});Ue.displayName="OfficerDetailModal";const We=G.memo(({photoData:r,onClose:i})=>{const[s,y]=G.useState(!1);if(!r||!r.url)return null;const{url:g,title:z,officerName:h,designation:c,timestamp:b,location:d,type:o}=r,w=d&&d.lat&&d.lng?`${parseFloat(d.lat).toFixed(6)}, ${parseFloat(d.lng).toFixed(6)}`:null,n=()=>{w&&(navigator.clipboard.writeText(w),y(!0),setTimeout(()=>y(!1),2e3))};return e.jsxs(m,{style:{position:"fixed",inset:0,background:"rgba(0, 0, 0, 0.85)",backdropFilter:"blur(16px)",WebkitBackdropFilter:"blur(16px)",zIndex:99999,display:"flex",alignItems:"center",justifyContent:"center",padding:24,animation:"fadeIn 0.2s ease-out"},onClick:i,children:[e.jsx(B,{size:"3",variant:"solid",color:"gray",highContrast:!0,style:{position:"absolute",top:24,right:24,borderRadius:"50%",cursor:"pointer",zIndex:10},onClick:l=>{l.stopPropagation(),i()},"aria-label":"Close photo preview",children:e.jsx(re,{style:{width:22,height:22}})}),e.jsxs(m,{style:{maxWidth:"90vw",maxHeight:"90vh",display:"flex",flexDirection:"column",alignItems:"center",background:"var(--color-panel-solid, var(--color-surface, #ffffff))",border:"1px solid var(--gray-a5)",borderRadius:"var(--radius-4)",boxShadow:"var(--shadow-6, 0 25px 60px -15px rgba(0, 0, 0, 0.5))",overflow:"hidden"},onClick:l=>l.stopPropagation(),children:[e.jsx(m,{p:"3",style:{width:"100%",borderBottom:"1px solid var(--gray-a4)",background:"var(--gray-a2)"},children:e.jsxs(a,{justify:"between",align:"center",gap:"3",px:"2",children:[e.jsxs(a,{align:"center",gap:"2",children:[e.jsx(K,{style:{color:"var(--blue-9)",width:18,height:18}}),e.jsxs(m,{children:[e.jsx(_,{size:"2",weight:"bold",style:{color:"var(--gray-12)"},children:h||"Officer Photo"}),c&&e.jsx(_,{size:"1",color:"gray",style:{display:"block"},children:c})]})]}),e.jsx(Q,{size:"1",color:o==="punchin"?"green":o==="punchout"?"red":"blue",variant:"solid",children:o==="punchin"?"Check-In Photo":o==="punchout"?"Check-Out Photo":z||"Verification Selfie"})]})}),e.jsx(m,{style:{display:"flex",alignItems:"center",justifyContent:"center",padding:16,maxHeight:"65vh",minWidth:320,maxWidth:720,overflow:"hidden"},children:e.jsx("img",{src:g,alt:"Officer Telemetry Verification",style:{maxWidth:"100%",maxHeight:"60vh",objectFit:"contain",borderRadius:"var(--radius-3)",border:"1px solid var(--gray-a4)",boxShadow:"var(--shadow-4)"}})}),e.jsx(m,{p:"3",style:{width:"100%",borderTop:"1px solid var(--gray-a4)",background:"var(--gray-a2)"},children:e.jsxs(a,{justify:"between",align:"center",gap:"3",wrap:"wrap",px:"2",children:[e.jsxs(a,{align:"center",gap:"4",wrap:"wrap",children:[b&&e.jsxs(a,{align:"center",gap:"1",children:[e.jsx(ee,{style:{color:"var(--purple-9)",width:14,height:14}}),e.jsx(_,{size:"1",style:{color:"var(--gray-12)"},children:b})]}),w&&e.jsxs(a,{align:"center",gap:"2",children:[e.jsx(xe,{style:{color:"var(--green-9)",width:14,height:14}}),e.jsx(_,{size:"1",style:{color:"var(--gray-12)",fontFamily:"monospace"},children:w}),e.jsxs(O,{size:"1",variant:"ghost",color:"gray",style:{cursor:"pointer",padding:"0 4px",height:20},onClick:n,children:[s?e.jsx(ae,{style:{color:"var(--green-9)"}}):e.jsx(me,{}),e.jsx("span",{style:{fontSize:10},children:s?"Copied":"Copy"})]})]})]}),e.jsx("a",{href:g,target:"_blank",rel:"noopener noreferrer",download:!0,style:{textDecoration:"none"},children:e.jsxs(O,{size:"1",variant:"soft",color:"blue",style:{cursor:"pointer"},children:[e.jsx(ir,{}),"Download HD"]})})]})})]})]})});We.displayName="PhotoTelemetryLightbox";const ur=G.memo(({selectedDate:r,updateMap:i})=>{const[s,y]=p.useState([]),[g,z]=p.useState([]),[h,c]=p.useState(!0),[b,d]=p.useState(!1),[o,w]=p.useState(null),[n,l]=p.useState(""),[f,t]=p.useState("all"),[v,x]=p.useState(()=>localStorage.getItem("guardian_map_tile_id")||"voyager"),[S,T]=p.useState({geofences:!0,waypoints:!0,trajectories:!0}),[R,I]=p.useState(!0),[C,j]=p.useState(!1),[F,u]=p.useState(null),[E,A]=p.useState(null),[P,M]=p.useState(null),[D,V]=p.useState(0),[U,q]=p.useState(null),[J,ve]=p.useState(!0),[De,je]=p.useState(pe),le=p.useRef(null);p.useRef(null);const Be=p.useCallback(k=>{x(k),localStorage.setItem("guardian_map_tile_id",k)},[]),Ge=p.useCallback(k=>{T($=>({...$,[k]:!$[k]}))},[]),Y=p.useCallback(async(k=!1)=>{if(r){k?d(!0):c(!0);try{const $=route("getUserLocationsForDate",{date:r.split("T")[0],_t:Date.now()}),Z=await fetch($);if(!Z.ok)throw new Error(`HTTP ${Z.status}: Failed to fetch user locations`);const H=await Z.json(),oe=Array.isArray(H.locations)?H.locations:[],X=Array.isArray(H.attendance_type_configs)?H.attendance_type_configs:[];y(oe),z(X),w(new Date),je(pe)}catch($){console.error("Failed to load team locations:",$)}finally{c(!1),d(!1)}}},[r]);p.useEffect(()=>{Y(!1)},[r,i,Y]),p.useEffect(()=>{if(!J)return;const k=setInterval(()=>{je($=>$<=1?(Y(!0),pe):$-1)},1e3);return()=>clearInterval(k)},[J,Y]);const we=p.useMemo(()=>{const k=s.length;let $=0,Z=0;return s.forEach(H=>{H.status==="active"?$++:Z++}),{total:k,checkedIn:$,active:$,completed:Z}},[s]),te=p.useMemo(()=>s.filter(k=>{var $,Z,H,oe;if(f==="active"&&k.status!=="active"||f==="completed"&&k.status==="active")return!1;if(n){const X=n.toLowerCase(),Ye=($=k.name)==null?void 0:$.toLowerCase().includes(X),Qe=(Z=k.employee_id)==null?void 0:Z.toLowerCase().includes(X),Je=(H=k.designation)==null?void 0:H.toLowerCase().includes(X),Xe=(oe=k.department)==null?void 0:oe.toLowerCase().includes(X);if(!Ye&&!Qe&&!Je&&!Xe)return!1}return!0}),[s,f,n]),Ze=p.useMemo(()=>o?o.toLocaleTimeString("en-US",{hour:"2-digit",minute:"2-digit",second:"2-digit",hour12:!0}):null,[o]),ke=p.useMemo(()=>{if(!r)return"Invalid Date";try{return new Date(r).toLocaleString("en-US",{weekday:"long",year:"numeric",month:"long",day:"numeric"})}catch{return r}},[r]),Ce=p.useCallback(k=>{u(k.user_id);const $=k.punchin_location||k.punchout_location||k.location;$&&$.lat&&$.lng&&q([parseFloat($.lat),parseFloat($.lng)])},[]),He=p.useCallback(k=>{q(k)},[]),qe=p.useCallback(()=>{V(k=>k+1)},[]),Ve=p.useCallback(()=>{le.current&&(document.fullscreenElement?(document.exitFullscreen(),j(!1)):(le.current.requestFullscreen().catch(k=>{console.warn("Fullscreen error:",k)}),j(!0)))},[]);return p.useEffect(()=>{const k=()=>{j(!!document.fullscreenElement)};return document.addEventListener("fullscreenchange",k),()=>document.removeEventListener("fullscreenchange",k)},[]),e.jsxs(m,{children:[e.jsxs(er,{mb:"4",children:[e.jsx(m,{p:"4",style:{borderBottom:"1px solid var(--gray-a4)"},children:e.jsxs(a,{justify:"between",align:"center",gap:"3",wrap:"wrap",children:[e.jsxs(a,{align:"center",gap:"3",children:[e.jsx(m,{style:{padding:10,borderRadius:"var(--radius-3)",background:"linear-gradient(135deg, var(--blue-a3), var(--blue-a4))",border:"1px solid var(--blue-a6)",width:44,height:44,flexShrink:0,display:"flex",alignItems:"center",justifyContent:"center",boxShadow:"0 2px 8px rgba(0,0,0,0.06)"},children:e.jsx(ue,{style:{color:"var(--blue-9)",width:22,height:22}})}),e.jsxs(m,{children:[e.jsx(Le,{size:"4",style:{letterSpacing:"-0.02em"},children:"Team Locations & Live GIS Command Center"}),e.jsx(_,{size:"2",color:"gray",children:ke})]})]}),e.jsxs(O,{variant:"surface",size:"1",color:"blue",onClick:()=>Y(!1),disabled:h||b,style:{cursor:"pointer"},children:[e.jsx(ge,{className:h||b?"animate-spin":""}),"Refresh Live Feed"]})]})}),e.jsx(Te,{stats:we,lastUpdateText:Ze,isPolling:J,secondsLeft:De}),e.jsx(m,{p:"4",children:h?e.jsx(a,{align:"center",justify:"center",style:{height:"72vh",border:"1px solid var(--gray-a4)",borderRadius:"var(--radius-3)",background:"var(--gray-a2)"},children:e.jsxs(a,{direction:"column",align:"center",gap:"3",children:[e.jsx(Ke,{size:"3"}),e.jsx(_,{size:"2",weight:"medium",color:"gray",children:"Loading team coordinates & GIS boundaries..."})]})}):s.length===0?e.jsxs(a,{direction:"column",align:"center",justify:"center",gap:"3",p:"6",style:{height:"72vh",border:"1px solid var(--gray-a4)",borderRadius:"var(--radius-3)",background:"var(--gray-a2)"},children:[e.jsx(ue,{style:{width:64,height:64,color:"var(--gray-7)"}}),e.jsx(Le,{size:"4",children:"No Team Location Records Found"}),e.jsxs(_,{size:"2",color:"gray",align:"center",style:{maxWidth:420},children:["No check-in or patrol coordinates recorded for ",ke,". Ensure team members have logged attendance via mobile GPS or check a different date."]}),e.jsxs(O,{variant:"outline",onClick:()=>Y(!1),children:[e.jsx(ge,{})," Refresh Data"]})]}):e.jsxs(m,{ref:le,style:{position:"relative",height:C?"100vh":"72vh",borderRadius:C?0:"var(--radius-3)",overflow:"hidden",border:C?"none":"1px solid var(--gray-a5)",boxShadow:"0 8px 30px rgba(0,0,0,0.12)"},children:[e.jsx($e,{searchQuery:n,onSearchChange:l,statusFilter:f,onStatusFilterChange:t,stats:we,currentTileId:v,onTileChange:Be,layerVisibility:S,onToggleLayer:Ge,onFitBounds:qe,onRefresh:()=>Y(!0),isRefreshing:b,isDrawerOpen:R,onToggleDrawer:()=>I(k=>!k),isFullscreen:C,onToggleFullscreen:Ve}),e.jsxs(Me,{currentTileId:v,users:te,attendanceTypeConfigs:g,fitBoundsTrigger:D,flyToCoords:U,children:[e.jsx(Ae,{attendanceTypeConfigs:g,users:te,layerVisibility:S}),e.jsx(Ne,{users:te,selectedUserId:F,onSelectOfficer:Ce,onOpenTelemetry:A,onOpenPhoto:M,layerVisibility:S})]}),e.jsx(Oe,{isOpen:R,onClose:()=>I(!1),users:te,selectedUserId:F,onSelectOfficer:Ce,onOpenTelemetry:A,onOpenPhoto:M})]})})]}),E&&e.jsx(Ue,{officer:E,selectedDate:r,onClose:()=>A(null),onOpenPhoto:M,onFocusMap:He}),P&&e.jsx(We,{photoData:P,onClose:()=>M(null)})]})});ur.displayName="UserLocationsCard";export{ur as U};
