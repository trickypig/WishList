using System.Globalization;

namespace WishList.Converters;

/// <summary>
/// Converts a bool (IsBusy) to text. Parameter format: "BusyText|IdleText"
/// </summary>
public class BusyTextConverter : IValueConverter
{
    public object? Convert(object? value, Type targetType, object? parameter, CultureInfo culture)
    {
        var isBusy = value is true;
        var parts = (parameter as string)?.Split('|') ?? ["Loading...", "Submit"];
        return isBusy ? parts[0] : (parts.Length > 1 ? parts[1] : parts[0]);
    }

    public object? ConvertBack(object? value, Type targetType, object? parameter, CultureInfo culture)
    {
        throw new NotSupportedException();
    }
}
