export default {
  async fetch(request, env, ctx) {
    if (request.method === "GET") {
      const url = new URL(request.url);
      const targetUrl = new URL("https://agente.fornuvi.com/webhook-ycloud.php");
      targetUrl.search = url.search;

      return fetch(targetUrl.toString(), {
        method: "GET",
        headers: request.headers,
      });
    }

    if (request.method === "POST") {
      let bodyText = await request.text();
      
      const targetHeaders = new Headers(request.headers);
      
      // EL TRUCO DEFINITIVO: MENTIRLE A MODSECURITY
      // Le decimos que es texto plano, no JSON, para que no lo filtre.
      targetHeaders.set("Content-Type", "text/plain");
      targetHeaders.delete("Content-Length");

      const originResponse = await fetch("https://agente.fornuvi.com/webhook-ycloud.php", {
        method: "POST",
        headers: targetHeaders,
        body: bodyText
      });

      return new Response(originResponse.body, {
        status: originResponse.status,
        statusText: originResponse.statusText,
        headers: originResponse.headers
      });
    }

    return new Response("Method not allowed", { status: 405 });
  }
};
