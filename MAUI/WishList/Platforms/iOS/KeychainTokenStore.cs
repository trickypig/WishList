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

    /// <summary>
    /// Saves the token; false if the keychain refused (for example a signing/entitlement problem).
    /// Adds first and updates an existing item in place, so a failure never deletes the current token.
    /// </summary>
    public static bool Set(string token)
    {
        var data = NSData.FromString(token, NSStringEncoding.UTF8);
        var record = new SecRecord(SecKind.GenericPassword)
        {
            Service = Service,
            Account = Constants.TokenKey,
            // Readable by the share extension whenever the phone has been unlocked once
            Accessible = SecAccessible.AfterFirstUnlock,
            ValueData = data
        };
        var status = SecKeyChain.Add(record);
        if (status == SecStatusCode.DuplicateItem)
            status = SecKeyChain.Update(Query(), new SecRecord(SecKind.GenericPassword) { ValueData = data });
        return status == SecStatusCode.Success;
    }

    public static void Remove() => SecKeyChain.Remove(Query());
}
