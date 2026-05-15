package ai.mirzatech.maya

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.os.Build

/**
 * BootReceiver · auto-start the BridgeService when the phone boots so Mo doesn't
 * have to open the Maya app to wake the bridge up.
 */
class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val action = intent.action ?: return
        if (action == Intent.ACTION_BOOT_COMPLETED ||
            action == Intent.ACTION_LOCKED_BOOT_COMPLETED) {
            val svcIntent = Intent(context, BridgeService::class.java)
            try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    context.startForegroundService(svcIntent)
                } else {
                    context.startService(svcIntent)
                }
            } catch (_: Exception) {
                // First boot may not have full permissions yet · Mo opens the app once after boot to bootstrap
            }
        }
    }
}
