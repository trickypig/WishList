using System.Globalization;

namespace WishList.Converters;

/// <summary>
/// Picks a size from "full|compact" in the parameter, using the compact one when the bound value is true.
/// Used to shrink item cards that sit inside a group. Returns a Thickness ("16" or "16,4") when bound to Padding or Margin.
/// </summary>
public class CompactSizeConverter : IValueConverter
{
    public object? Convert(object? value, Type targetType, object? parameter, CultureInfo culture)
    {
        var parts = (parameter as string ?? "0|0").Split('|');
        var picked = value is true && parts.Length > 1 ? parts[1] : parts[0];
        if (targetType != typeof(Thickness))
            return double.Parse(picked, CultureInfo.InvariantCulture);

        // Thickness accepts XAML-style "16" (all sides) or "16,4" (horizontal, vertical)
        var n = picked.Split(',').Select(p => double.Parse(p, CultureInfo.InvariantCulture)).ToArray();
        return n.Length == 2 ? new Thickness(n[0], n[1]) : new Thickness(n[0]);
    }

    public object? ConvertBack(object? value, Type targetType, object? parameter, CultureInfo culture) =>
        throw new NotSupportedException();
}
