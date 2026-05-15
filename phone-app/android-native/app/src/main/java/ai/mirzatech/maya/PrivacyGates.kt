package ai.mirzatech.maya

import android.content.Context
import java.io.File

/**
 * PrivacyGates · file-flag controls matching the Windows bridge pattern.
 *
 * eyes_enabled: TRUE if `~/.maya_bridge_eyes_off` does NOT exist (default ON).
 * hands_enabled: TRUE only if `~/.maya_bridge_hands_on` DOES exist (default OFF · explicit opt-in).
 *
 * On Android, "~" maps to the app's filesDir which is `/data/data/ai.mirzatech.maya/files`.
 */
object PrivacyGates {
    private const val EYES_OFF_FLAG_NAME = ".maya_bridge_eyes_off"
    private const val HANDS_ON_FLAG_NAME = ".maya_bridge_hands_on"

    fun eyesEnabled(ctx: Context): Boolean {
        return !File(ctx.filesDir, EYES_OFF_FLAG_NAME).exists()
    }

    fun handsEnabled(ctx: Context): Boolean {
        return File(ctx.filesDir, HANDS_ON_FLAG_NAME).exists()
    }

    fun setEyes(ctx: Context, enabled: Boolean) {
        val f = File(ctx.filesDir, EYES_OFF_FLAG_NAME)
        if (enabled) {
            if (f.exists()) f.delete()
        } else {
            f.writeText("disabled at ${System.currentTimeMillis()}\n")
        }
    }

    fun setHands(ctx: Context, enabled: Boolean) {
        val f = File(ctx.filesDir, HANDS_ON_FLAG_NAME)
        if (enabled) {
            f.writeText("enabled at ${System.currentTimeMillis()}\n")
        } else {
            if (f.exists()) f.delete()
        }
    }
}
