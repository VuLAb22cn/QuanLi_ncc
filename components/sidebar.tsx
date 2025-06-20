"use client"

import Link from "next/link"
import { usePathname } from "next/navigation"
import { cn } from "@/lib/utils"
import { Building, Users, FileText, DollarSign, CreditCard, Search, BookOpen, Star, Home, Wrench } from "lucide-react"

const navigation = [
  { name: "Dashboard", href: "/", icon: Home },
  { name: "Nhà cung cấp", href: "/suppliers", icon: Building },
  { name: "Dịch vụ", href: "/services", icon: Wrench },
  { name: "Hóa đơn", href: "/invoices", icon: FileText },
  { name: "Sổ quỹ", href: "/cashbook", icon: BookOpen },
  { name: "Công nợ", href: "/debts", icon: DollarSign },
  { name: "Thanh toán", href: "/payments", icon: CreditCard },
  { name: "Hợp đồng", href: "/contracts", icon: Users },
  { name: "Xếp hạng", href: "/rankings", icon: Star },
  { name: "Tìm kiếm", href: "/search", icon: Search },
]

export function Sidebar() {
  const pathname = usePathname()

  return (
    <div className="bg-white w-64 min-h-screen shadow-lg">
      <div className="p-6">
        <h1 className="text-xl font-bold text-gray-800">Quản lý NCC</h1>
      </div>
      <nav className="mt-6">
        {navigation.map((item) => {
          const isActive = pathname === item.href
          return (
            <Link
              key={item.name}
              href={item.href}
              className={cn(
                "flex items-center px-6 py-3 text-sm font-medium transition-colors",
                isActive
                  ? "bg-blue-50 text-blue-700 border-r-2 border-blue-700"
                  : "text-gray-600 hover:bg-gray-50 hover:text-gray-900",
              )}
            >
              <item.icon className="mr-3 h-5 w-5" />
              {item.name}
            </Link>
          )
        })}
      </nav>
    </div>
  )
}
