using WishList.Services;

namespace WishList;

public partial class App : Application
{
    private readonly IAuthService _authService;

    public App(IAuthService authService)
    {
        InitializeComponent();
        _authService = authService;
    }

    protected override Window CreateWindow(IActivationState? activationState)
    {
        var shell = new AppShell();
        var window = new Window(shell);

        shell.Loaded += async (s, e) =>
        {
            var restored = await _authService.TryRestoreSessionAsync();
            if (restored)
            {
                await shell.GoToAsync("//main/dashboard");
            }
            else
            {
                await shell.GoToAsync("//login");
            }
        };

        return window;
    }
}
