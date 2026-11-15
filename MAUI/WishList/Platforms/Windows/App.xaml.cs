using Microsoft.UI.Xaml;

// To learn more about WinUI, the WinUI project structure,
// and more about our project templates, see: http://aka.ms/winui-project-info.

namespace WishList.WinUI
{
    /// <summary>
    /// Provides application-specific behavior to supplement the default Application class.
    /// </summary>
    public partial class App : MauiWinUIApplication
    {
        /// <summary>Where crashes are written: %LOCALAPPDATA%\WishList\crash.log</summary>
        public static readonly string CrashLogPath = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "WishList", "crash.log");

        /// <summary>
        /// Initializes the singleton application object.  This is the first line of authored code
        /// executed, and as such is the logical equivalent of main() or WinMain().
        /// </summary>
        public App()
        {
            this.InitializeComponent();

            // A WinUI crash otherwise only reaches the event log as "0xc000027b" with no details
            UnhandledException += (s, e) => LogCrash("WinUI", e.Exception, e.Message);
            AppDomain.CurrentDomain.UnhandledException += (s, e) => LogCrash("AppDomain", e.ExceptionObject as Exception, null);
            TaskScheduler.UnobservedTaskException += (s, e) => LogCrash("Task", e.Exception, null);
        }

        protected override MauiApp CreateMauiApp() => MauiProgram.CreateMauiApp();

        private static void LogCrash(string source, Exception? ex, string? message)
        {
            try
            {
                Directory.CreateDirectory(Path.GetDirectoryName(CrashLogPath)!);
                File.AppendAllText(CrashLogPath,
                    $"==== {DateTime.Now:yyyy-MM-dd HH:mm:ss} [{source}] {message}{Environment.NewLine}{ex}{Environment.NewLine}{Environment.NewLine}");
            }
            catch
            {
                // Never let logging hide the original crash
            }
        }
    }

}
