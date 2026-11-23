using Foundation;
using Security;
using WishList.Helpers;

namespace WishList.Platforms.iOS;

/// <summary>
/// The login token, kept where both the app and its share extension can read it.
/// Items are added without naming an access group, so iOS stores them in the first
/// keychain-access-groups entry of the entitlements: the shared com.trickypig.wish.shared group.
/// Linked into the share extension, so this must not use MAUI.
/// </summary>
public static class KeychainTokenStore
{
    const string Service = "com.trickypig.wish.auth";

    static SecRecord Query() => new(SecKind.GenericPassword)
    {
        Service = Service,
        Account = Constants.TokenKey
    };

    public static string? Get()
    {
        var data = SecKeyChain.QueryAsData(Query(), false, out var status);
        return status == SecStatusCode.Success && data is not null
            ? NSString.FromData(data, NSStringEncoding.UTF8)?.ToString()
            : null;
    }

    public static void Set(string token)
    {
        Remove();
        var record = new SecRecord(SecKind.GenericPassword)
        {
            Service = Service,
            Account = Constants.TokenKey,
            // Readable by the share extension whenever the phone has been unlocked once
            Accessible = SecAccessible.AfterFirstUnlock,
            ValueData = NSData.FromString(token, NSStringEncoding.UTF8)
        };
        var status = SecKeyChain.Add(record);
        if (status != SecStatusCode.Success)
            throw new InvalidOperationException($"Couldn't save the login ({status}).");
    }

    public static void Remove() => SecKeyChain.Remove(Query());
}
