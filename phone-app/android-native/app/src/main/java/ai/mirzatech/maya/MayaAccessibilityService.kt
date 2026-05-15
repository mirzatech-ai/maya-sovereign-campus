package ai.mirzatech.maya

import android.accessibilityservice.AccessibilityService
import android.accessibilityservice.GestureDescription
import android.graphics.Path
import android.os.Bundle
import android.view.accessibility.AccessibilityEvent
import android.view.accessibility.AccessibilityNodeInfo

/**
 * MayaAccessibilityService · cross-app eyes + hands for Maya.
 *
 * Listens to window/click/text events across all apps. The BridgeServer reaches in
 * here via a static singleton to: get active window info, traverse the node tree
 * (find Chrome URL bar text, page headings, etc), perform gesture clicks, type into
 * focused fields.
 *
 * Privacy: respects the eyes_enabled / hands_enabled flag files. When eyes are off,
 * we still receive events (Android requires that for the service to stay registered)
 * but we don't expose them to the bridge.
 */
class MayaAccessibilityService : AccessibilityService() {
    @Volatile var lastEvent: AccessibilityEvent? = null
    @Volatile var lastWindowTitle: String = ""
    @Volatile var lastPackageName: String = ""

    override fun onServiceConnected() {
        super.onServiceConnected()
        instance = this
    }

    override fun onAccessibilityEvent(event: AccessibilityEvent?) {
        if (event == null) return
        if (!PrivacyGates.eyesEnabled(this)) return
        lastEvent = event
        if (event.eventType == AccessibilityEvent.TYPE_WINDOW_STATE_CHANGED) {
            lastWindowTitle = event.className?.toString() ?: ""
            lastPackageName = event.packageName?.toString() ?: ""
        }
    }

    override fun onInterrupt() {
        // Required override · no-op
    }

    override fun onDestroy() {
        instance = null
        super.onDestroy()
    }

    // ── Helpers used by BridgeServer ──────────────────────────────────────

    /** Return active window's package name + a best-effort title (from root node text traversal) */
    fun getActiveWindowInfo(): Map<String, Any?> {
        val rootNode = rootInActiveWindow
        val title = lastWindowTitle.ifEmpty { rootNode?.windowId?.toString() ?: "?" }
        val pkg = rootNode?.packageName?.toString() ?: lastPackageName.ifEmpty { "?" }
        val firstText = rootNode?.let { findFirstText(it) } ?: ""
        return mapOf(
            "ok" to true,
            "package" to pkg,
            "title" to title,
            "first_text" to firstText,
            "platform" to "android"
        )
    }

    /** Traverse node tree · return first non-empty text content */
    private fun findFirstText(node: AccessibilityNodeInfo, depth: Int = 0): String {
        if (depth > 6) return ""
        val text = node.text?.toString() ?: ""
        if (text.isNotBlank()) return text
        for (i in 0 until node.childCount) {
            val child = node.getChild(i) ?: continue
            val t = findFirstText(child, depth + 1)
            if (t.isNotBlank()) return t
        }
        return ""
    }

    /** Capture top-N visible text snippets from the active window (Chrome tab content, etc) */
    fun captureWindowText(maxSnippets: Int = 20): List<String> {
        if (!PrivacyGates.eyesEnabled(this)) return emptyList()
        val rootNode = rootInActiveWindow ?: return emptyList()
        val out = mutableListOf<String>()
        collectText(rootNode, out, maxSnippets, 0)
        return out
    }

    private fun collectText(node: AccessibilityNodeInfo, out: MutableList<String>, max: Int, depth: Int) {
        if (out.size >= max || depth > 10) return
        val text = node.text?.toString()?.trim() ?: ""
        if (text.length in 3..400 && !out.contains(text)) {
            out.add(text)
        }
        for (i in 0 until node.childCount) {
            val child = node.getChild(i) ?: continue
            collectText(child, out, max, depth + 1)
        }
    }

    /** Perform a tap gesture at (x, y) in screen coordinates */
    fun performClick(x: Float, y: Float): Boolean {
        if (!PrivacyGates.handsEnabled(this)) return false
        val path = Path()
        path.moveTo(x, y)
        val gesture = GestureDescription.Builder()
            .addStroke(GestureDescription.StrokeDescription(path, 0L, 100L))
            .build()
        return dispatchGesture(gesture, null, null)
    }

    /** Type text into the currently focused editable field */
    fun typeText(text: String): Boolean {
        if (!PrivacyGates.handsEnabled(this)) return false
        val rootNode = rootInActiveWindow ?: return false
        val focused = findFocusedEditable(rootNode) ?: return false
        val args = Bundle()
        args.putCharSequence(AccessibilityNodeInfo.ACTION_ARGUMENT_SET_TEXT_CHARSEQUENCE, text)
        return focused.performAction(AccessibilityNodeInfo.ACTION_SET_TEXT, args)
    }

    private fun findFocusedEditable(node: AccessibilityNodeInfo): AccessibilityNodeInfo? {
        if (node.isFocused && node.isEditable) return node
        for (i in 0 until node.childCount) {
            val child = node.getChild(i) ?: continue
            val f = findFocusedEditable(child)
            if (f != null) return f
        }
        return null
    }

    companion object {
        @Volatile var instance: MayaAccessibilityService? = null
            private set
    }
}
