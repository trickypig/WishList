using WishList.Models;

namespace WishList.Services;

public interface IAuthService
{
    User? CurrentUser { get; }
    bool IsAuthenticated { get; }
    Task<bool> TryRestoreSessionAsync();
    Task<User> LoginAsync(string email, string password);
    Task<User> RegisterAsync(string email, string password, string displayName);
    Task<User> GetMeAsync();
    void Logout();
    Task ChangePasswordAsync(string currentPassword, string newPassword, string confirmPassword);
    Task RequestPasswordResetAsync(string email);
    Task ResetPasswordAsync(string email, string code, string newPassword, string confirmPassword);
    event Action? AuthStateChanged;
}
