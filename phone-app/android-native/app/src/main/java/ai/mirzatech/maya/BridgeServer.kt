package ai.mirzatech.maya

import android.content.Context
import android.util.Base64
import fi.iki.elonen.NanoHTTPD
import org.json.JSONArray
import org.json.JSONObject
import java.io.File

/**
 * BridgeServer · NanoHTTPD on 127.0.0.1:8765 inside the Maya APK.
 *
 * Mirrors the Windows bridge API surface so Maya OS PWA running on this same phone
 * (or a peer device via tunnel) can hit the same endpoints regardless of host OS:
 *
 *   GET  /health                       no auth · capabilities + version
 *   GET  /token                        no auth · echoes the bridge token
 *   GET  /eyes/status                  token  · current eyes flag state
 *   POST /eyes/on  · /eyes/off         token  · flip eyes flag
 *   GET  /hands/status                 token  · current hands flag state
 *   POST /hands/on · /hands/off        token  · flip hands flag
 *   GET  /active-window                token+eyes · package + title + first_text
 *   GET  /window-text                  token+eyes · array of visible text snippets
 *   POST /click  {x, y}                token+hands · gesture click
 *   POST /type   {text}                token+hands · SET_TEXT on focused field
 *
 * Token: auto-generated 48 random bytes on first start · stored in
 *   /data/data/ai.mirzatech.maya/files/.maya_bridge_token (mode 0600)
 *   Maya OS PWA fetches /token from localhost (only routable on-device) and saves it.
 */
class BridgeServer(private val ctx: Context, port: Int = 8765) : NanoHTTPD("127.0.0.1", port) {

    val token: String by lazy {
        val f = File(ctx.filesDir, ".maya_bridge_token")
        if (f.exists()) f.readText().trim()
        else {
            val bytes = ByteArray(48)
            java.security.SecureRandom().nextBytes(bytes)
            val t = Base64.encodeToString(bytes, Base64.URL_SAFE or Base64.NO_PADDING or Base64.NO_WRAP)
            f.writeText(t)
            t
        }
    }

    override fun serve(session: IHTTPSession): Response {
        val uri = session.uri ?: "/"
        val method = session.method
        return try {
            when {
                uri == "/health"        -> health()
                uri == "/token"         -> json(mapOf("ok" to true, "token" to token))
                uri == "/eyes/status"   -> requireToken(session)?.let { it } ?: json(mapOf("ok" to true, "enabled" to PrivacyGates.eyesEnabled(ctx)))
                uri == "/eyes/on"       -> { requireToken(session)?.let { return it }; PrivacyGates.setEyes(ctx, true);  json(mapOf("ok" to true, "enabled" to true)) }
                uri == "/eyes/off"      -> { requireToken(session)?.let { return it }; PrivacyGates.setEyes(ctx, false); json(mapOf("ok" to true, "enabled" to false)) }
                uri == "/hands/status"  -> requireToken(session)?.let { it } ?: json(mapOf("ok" to true, "enabled" to PrivacyGates.handsEnabled(ctx)))
                uri == "/hands/on"      -> { requireToken(session)?.let { return it }; PrivacyGates.setHands(ctx, true);  json(mapOf("ok" to true, "enabled" to true)) }
                uri == "/hands/off"     -> { requireToken(session)?.let { return it }; PrivacyGates.setHands(ctx, false); json(mapOf("ok" to true, "enabled" to false)) }
                uri == "/active-window" -> {
                    requireToken(session)?.let { return it }
                    if (!PrivacyGates.eyesEnabled(ctx)) return error(403, "eyes_off")
                    val svc = MayaAccessibilityService.instance ?: return error(503, "accessibility service not connected · enable in Android Settings → Accessibility → Maya OS")
                    json(svc.getActiveWindowInfo())
                }
                uri == "/window-text" -> {
                    requireToken(session)?.let { return it }
                    if (!PrivacyGates.eyesEnabled(ctx)) return error(403, "eyes_off")
                    val svc = MayaAccessibilityService.instance ?: return error(503, "accessibility service not connected")
                    val texts = svc.captureWindowText(30)
                    val arr = JSONArray()
                    texts.forEach { arr.put(it) }
                    val out = JSONObject().put("ok", true).put("count", texts.size).put("texts", arr)
                    Response.newFixedLengthResponse(Response.Status.OK, "application/json", out.toString())
                }
                uri == "/click" && method == Method.POST -> {
                    requireToken(session)?.let { return it }
                    val body = readBody(session)
                    val j = JSONObject(body.ifEmpty { "{}" })
                    val x = j.optDouble("x", 0.0).toFloat()
                    val y = j.optDouble("y", 0.0).toFloat()
                    val svc = MayaAccessibilityService.instance ?: return error(503, "accessibility service not connected")
                    val ok = svc.performClick(x, y)
                    json(mapOf("ok" to ok, "x" to x, "y" to y))
                }
                uri == "/type" && method == Method.POST -> {
                    requireToken(session)?.let { return it }
                    val body = readBody(session)
                    val j = JSONObject(body.ifEmpty { "{}" })
                    val text = j.optString("text", "")
                    if (text.isEmpty()) return error(400, "text required")
                    val svc = MayaAccessibilityService.instance ?: return error(503, "accessibility service not connected")
                    val ok = svc.typeText(text)
                    json(mapOf("ok" to ok, "length" to text.length))
                }
                else -> error(404, "unknown endpoint: $uri")
            }
        } catch (e: Exception) {
            error(500, "internal error: ${e.message}")
        }
    }

    private fun requireToken(session: IHTTPSession): Response? {
        val h = session.headers["x-maya-bridge-token"] ?: ""
        if (h == token) return null  // null means OK to proceed
        return error(401, "bad bridge token")
    }

    private fun readBody(session: IHTTPSession): String {
        val map = HashMap<String, String>()
        return try {
            session.parseBody(map)
            map["postData"] ?: ""
        } catch (e: Exception) { "" }
    }

    private fun health(): Response {
        val obj = JSONObject()
            .put("ok", true)
            .put("service", "maya_device_bridge_android")
            .put("version", BuildConfigShim.versionName)
            .put("platform", JSONObject().put("kind", "android").put("label", "Android").put("is_termux", false).put("is_android", true))
            .put("device_hint", android.os.Build.MODEL ?: "android")
            .put("capabilities", JSONObject()
                .put("files", false)
                .put("shell", false)
                .put("ssh", false)
                .put("termux_api", false)
                .put("clipboard", false)
                .put("accessibility", MayaAccessibilityService.instance != null)
                .put("eyes_enabled", PrivacyGates.eyesEnabled(ctx))
                .put("hands_enabled", PrivacyGates.handsEnabled(ctx)))
            .put("token_required", true)
        return Response.newFixedLengthResponse(Response.Status.OK, "application/json", obj.toString())
    }

    private fun json(map: Map<String, Any?>): Response {
        val obj = JSONObject(map.mapValues { (_, v) ->
            when (v) {
                is Map<*, *> -> JSONObject(v as Map<String, Any?>)
                else -> v
            }
        })
        return Response.newFixedLengthResponse(Response.Status.OK, "application/json", obj.toString())
    }

    private fun error(code: Int, msg: String): Response {
        val obj = JSONObject().put("ok", false).put("error", msg)
        val st = when (code) {
            400 -> Response.Status.BAD_REQUEST
            401 -> Response.Status.UNAUTHORIZED
            403 -> Response.Status.FORBIDDEN
            404 -> Response.Status.NOT_FOUND
            503 -> Response.Status.SERVICE_UNAVAILABLE
            else -> Response.Status.INTERNAL_ERROR
        }
        return Response.newFixedLengthResponse(st, "application/json", obj.toString())
    }
}

/** Tiny shim so BridgeServer doesn't depend on the generated BuildConfig class */
object BuildConfigShim {
    const val versionName = "0.1.0-phase3b-alpha"
}
