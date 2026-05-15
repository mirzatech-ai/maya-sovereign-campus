package ai.mirzatech.maya

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat

/**
 * BridgeService · Android foreground service · keeps NanoHTTPD bridge alive.
 *
 * Android kills background services aggressively. The TWA App ID + a persistent
 * foreground notification let Maya's bridge keep serving on 127.0.0.1:8765 even
 * when the user backgrounds the app. Tap the notification → opens Maya OS to
 * Campus where Mo can toggle eyes/hands.
 */
class BridgeService : Service() {
    private var server: BridgeServer? = null

    override fun onCreate() {
        super.onCreate()
        createChannel()
        startInForeground()
        startBridge()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        // Sticky: if Android kills us, restart
        return START_STICKY
    }

    override fun onDestroy() {
        try { server?.stop() } catch (_: Exception) {}
        server = null
        super.onDestroy()
    }

    override fun onBind(intent: Intent?): IBinder? = null

    private fun startBridge() {
        try {
            val srv = BridgeServer(this, 8765)
            srv.start(5000, true)
            server = srv
        } catch (e: Exception) {
            // Port in use OR another error · service stays alive as a notification anyway so Mo sees state
        }
    }

    private fun createChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val nm = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            val ch = NotificationChannel(
                CHANNEL_ID,
                getString(R.string.bridge_service_channel_name),
                NotificationManager.IMPORTANCE_LOW
            )
            ch.description = getString(R.string.bridge_service_channel_description)
            ch.setShowBadge(false)
            nm.createNotificationChannel(ch)
        }
    }

    private fun startInForeground() {
        val openIntent = Intent(this, MainActivity::class.java)
        val pi = PendingIntent.getActivity(
            this, 0, openIntent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )
        val notif: Notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle(getString(R.string.bridge_notification_title))
            .setContentText(getString(R.string.bridge_notification_text))
            .setSmallIcon(android.R.drawable.ic_menu_view)
            .setContentIntent(pi)
            .setOngoing(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .build()

        if (Build.VERSION.SDK_INT >= 34) {
            startForeground(NOTIFICATION_ID, notif, ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE)
        } else {
            startForeground(NOTIFICATION_ID, notif)
        }
    }

    companion object {
        private const val CHANNEL_ID = "maya_bridge_channel"
        private const val NOTIFICATION_ID = 4711
    }
}
