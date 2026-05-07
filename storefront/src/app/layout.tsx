import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "DZ SaaS Commerce Storefront",
  description: "Storefront for Algerian SaaS commerce stores.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="ar-DZ" dir="rtl">
      <body>{children}</body>
    </html>
  );
}
