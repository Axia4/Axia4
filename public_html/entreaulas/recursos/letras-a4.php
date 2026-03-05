<?php
ini_set("display_errors", 0);
$PAGE_TITLE = "EntreAulas - Letras para dibujar";
require_once "../_incl/pre-body.php"; ?>
<div class="card pad">
    <h2>Generador de Letras A4 con varios estilos para imprimir</h2>
    
    <div id="a4-generator"></div>

    <style>
    #a4-generator{
        font-family: Arial, sans-serif;
    }
    #a4-generator .controls{
        background:#fff;
        padding:15px;
        border-radius:8px;
        box-shadow:0 4px 12px rgba(0,0,0,0.1);
        margin-bottom:20px;
        max-width:500px;
        border: 5px solid green;
    }
    #a4-generator textarea,
    #a4-generator input,
    #a4-generator select,
    #a4-generator button{
        width:100%;
        margin:5px 0;
        padding:6px;
    }
    </style>

    <script>

    // =====================
    // FUENTES
    // =====================
    const externalFonts = [
        { name: "Arial" },
        { name: "Times New Roman" },
        { name: "Courier New" },
        { name: "Impact" },
        { name: "Verdana" },

        { name: "Roboto", url: "https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" },
        { name: "Lobster", url: "https://fonts.googleapis.com/css2?family=Lobster&display=swap" },
        { name: "Pacifico", url: "https://fonts.googleapis.com/css2?family=Pacifico&display=swap" },
        { name: "Oswald", url: "https://fonts.googleapis.com/css2?family=Oswald:wght@400;700&display=swap" },
        { name: "Montserrat", url: "https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" },

        { name: "Cloud Sketch", file: "_resources/fonts/Cloud%20Sketch.ttf" },
        { name: "AlphaClouds", file: "_resources/fonts/AlphaClouds.ttf" },
        { name: "Cotton Cloud", file: "_resources/fonts/Cotton%20Cloud.ttf" },
        { name: "Doodle Gum", file: "_resources/fonts/Doodle%20Gum.ttf" },
        { name: "Plante", file: "_resources/fonts/Plante.ttf" }
    ];

    // Cargar Google Fonts en documento principal
    externalFonts.forEach(f=>{
        if(f.url){
            const link=document.createElement("link");
            link.rel="stylesheet";
            link.href=f.url;
            document.head.appendChild(link);
        }
    });

    // Registrar TTF en documento principal
    externalFonts.forEach(f=>{
        if(f.file){
            const style=document.createElement("style");
            style.innerHTML=`
            @font-face {
                font-family: '${f.name}';
                src: url('${f.file}') format('truetype');
                font-display: block;
            }`;
            document.head.appendChild(style);
        }
    });

    (function(){

    const container=document.getElementById("a4-generator");

    container.innerHTML=`
    <div class="controls">
    <label>Letras:</label>
    <textarea id="letters">ABC</textarea>

    <label>Fuente:</label>
    <select id="fontFamily"></select>

    <label>Color letra:</label>
    <input type="color" id="fontColor" value="#000000">

    <label><input type="checkbox" id="noFill"> Solo contorno</label>

    <label>Color contorno:</label>
    <input type="color" id="strokeColor" value="#000000">

    <label>Grosor contorno base (0 para quitar):</label>
    <input type="number" id="strokeWidth" value="0" max="5" min="0">

    <button id="generate">Imprimir</button>
    </div>
    `;

    const fontSelect=container.querySelector("#fontFamily");

    externalFonts.forEach(f=>{
        const o=document.createElement("option");
        o.value=f.name;
        o.textContent=f.name;
        o.style.fontFamily=f.name;
        fontSelect.appendChild(o);
    });

    container.querySelector("#generate").addEventListener("click",generateVectorPDF);

    // =====================
    // FUNCIÓN PRINCIPAL
    // =====================
    async function generateVectorPDF(){

        const letters=container.querySelector("#letters").value
            .split('')
            .filter(c=>c.trim()!=='');

        const fontFamily=container.querySelector("#fontFamily").value;
        const fontColor=container.querySelector("#fontColor").value;
        const strokeColor=container.querySelector("#strokeColor").value;
        const baseStroke=parseFloat(container.querySelector("#strokeWidth").value);
        const noFill=container.querySelector("#noFill").checked;

        const a4Width=210;
        const a4Height=297;

        // Esperar fuente en documento principal (para medir)
        await document.fonts.load(`100px "${fontFamily}"`);
        await document.fonts.ready;

        const iframe=document.createElement("iframe");
        iframe.style.position="fixed";
        iframe.style.width="0";
        iframe.style.height="0";
        iframe.style.border="0";
        document.body.appendChild(iframe);

        const doc=iframe.contentWindow.document;
        doc.open();

        // Inyectar fuentes también en el iframe
        doc.write(`
        <html>
        <head>

        ${externalFonts.map(f=>{
            if(f.url){
                return `<link href="${f.url}" rel="stylesheet">`;
            }
            if(f.file){
                return `
                <style>
                @font-face {
                    font-family: '${f.name}';
                    src: url('${f.file}') format('truetype');
                    font-display: block;
                }
                </style>
                `;
            }
            return '';
        }).join("")}

        <style>
            body{margin:0;}
            svg{width:100%;height:100%;}
        </style>

        </head>
        <body>
        `);

        for(const char of letters){

            const tempSvg=document.createElementNS("http://www.w3.org/2000/svg","svg");
            const tempText=document.createElementNS("http://www.w3.org/2000/svg","text");

            tempText.textContent=char;
            tempText.setAttribute("font-family",fontFamily);
            tempText.setAttribute("font-size","100");

            tempSvg.appendChild(tempText);
            tempSvg.style.position="absolute";
            tempSvg.style.visibility="hidden";
            document.body.appendChild(tempSvg);

            const bbox=tempText.getBBox();
            document.body.removeChild(tempSvg);

            const scaleX=(a4Width*0.95)/bbox.width;
            const scaleY=(a4Height*0.95)/bbox.height;
            const finalFontSize=100*Math.min(scaleX,scaleY);
            const finalStroke=(baseStroke/100)*finalFontSize;

            doc.write(`
            <svg xmlns="http://www.w3.org/2000/svg"
                width="${a4Width}mm"
                height="${a4Height}mm"
                viewBox="0 0 ${a4Width} ${a4Height}">
                <text x="50%" y="50%"
                    dominant-baseline="middle"
                    text-anchor="middle"
                    font-family="${fontFamily}"
                    font-size="${finalFontSize}"
                    fill="${noFill?'none':fontColor}"
                    stroke="${strokeColor}"
                    stroke-width="${finalStroke}">
                    ${char}
                </text>
            </svg>
            <div style="page-break-after:always;"></div>
            `);
        }

        doc.write("</body></html>");
        doc.close();

        // Esperar fuentes dentro del iframe antes de imprimir
        await iframe.contentWindow.document.fonts.ready;

        iframe.contentWindow.focus();
        iframe.contentWindow.print();

        setTimeout(()=>document.body.removeChild(iframe),1500);
    }

    })();
    </script>
</div>
<?php require_once "../_incl/post-body.php"; ?>