package ai.mirzatech.maya

import android.content.ComponentName
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.provider.Settings
import androidx.appcompat.app.AppCompatActivity
import androidx.browser.customtabs.CustomTabsClient
import androidx.browser.customtabs.CustomTabsServiceConnection
import androidx.browser.trusted.TrustedWebActivityIntentBuilder

/**
 * MainActivity · launches Maya OS PWA in a Trusted Web Activity (TWA).
 *
 * Flow:
 * 1. On launch, kick off the BridgeService so the local NanoHTTPD server starts.
 * 2. Connect to Custom Tabs Service · launch TWA pointing at Mo's PWA.
 * 3. Pass any incoming SEND share data as URL query params (share-to-Maya support).
 *
 * Falls back gracefully if Custom Tabs / TWA isn't available (e.g. no Chrome on phone) — the
 * intent just opens the URL in the system default browser.
 */
class MainActivity : AppCompatActivity() {
    private val packageName_Chrome = "com.android.chrome"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        // 1. Start the bridge service (idempotent · starts the foreground service if not running)
        try {
            startForegroundService(Intent(this, BridgeService::class.java))
        } catch (e: Exception) {
            // Service can't start (e.g. permission missing); proceed to PWA anyway
        }

        // 2. Build the URL · include shared data if Mo launched us via Android Share
        val baseUrl = buildUrlFromShareIntent(intent)

        // 3. Try TWA, fall back to system browser
        launchUrl(baseUrl)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        launchUrl(buildUrlFromShareIntent(intent))
    }

    /** Build the URL the TWA will load · merges Android SEND data into Maya OS share params */
    private fun buildUrlFromShareIntent(intent: Intent?): Uri {
        val base = "https://iamsuperio.cloud/maya-os/"
        if (intent?.action != Intent.ACTION_SEND) return Uri.parse(base)
        val sharedText = intent.getStringExtra(Intent.EXTRA_TEXT) ?: ""
        val sharedTitle = intent.getStringExtra(Intent.EXTRA_SUBJECT) ?: ""
        if (sharedText.isEmpty() && sharedTitle.isEmpty()) return Uri.parse(base)
        // Parse a URL out of the shared text if present, otherwise treat as text
        val urlRegex = Regex("https?://\\S+")
        val foundUrl = urlRegex.find(sharedText)?.value ?: ""
        val builder = Uri.parse(base).buildUpon()
        if (foundUrl.isNotEmpty())  builder.appendQueryParameter("shared_url", foundUrl)
        if (sharedText.isNotEmpty()) builder.appendQueryParameter("shared_text", sharedText)
        if (sharedTitle.isNotEmpty()) builder.appendQueryParameter("shared_title", sharedTitle)
        return builder.build()
    }

    private fun launchUrl(uri: Uri) {
        // Attempt TWA via Custom Tabs Service connection
        val connection = object : CustomTabsServiceConnection() {
            override fun onCustomTabsServiceConnected(name: ComponentName, client: CustomTabsClient) {
                client.warmup(0)
                val session = client.newSession(null)
                val twaBuilder = TrustedWebActivityIntentBuilder(uri)
                val twaIntent = twaBuilder.build(
                    if (session != null) androidx.browser.customtabs.CustomTabsIntent.Builder(session).build()
                    else androidx.browser.customtabs.CustomTabsIntent.Builder().build()
                )
                startActivity(twaIntent.intent)
                finish()
            }
            override fun onServiceDisconnected(name: ComponentName) {}
        }
        val bound = CustomTabsClient.bindCustomTabsService(this, packageName_Chrome, connection)
        if (!bound) {
            // Fallback: open in default browser
            val viewIntent = Intent(Intent.ACTION_VIEW, uri)
            viewIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            startActivity(viewIntent)
            finish()
        }
    }

    /** Helper · open Android accessibility settings so Mo can grant the service permission */
    @Suppress("unused")
    private fun openAccessibilitySettings() {
        val intent = Intent(Settings.ACTION_ACCESSIBILITY_SETTINGS)
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        startActivity(intent)
    }
}
