"use client"

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Users, FileText, DollarSign, TrendingUp, Building } from "lucide-react"
import Link from "next/link"

export default function Dashboard() {
  const stats = [
    {
      title: "Tổng nhà cung cấp",
      value: "156",
      icon: Users,
      change: "+12%",
      changeType: "positive",
    },
    {
      title: "Hóa đơn tháng này",
      value: "89",
      icon: FileText,
      change: "+8%",
      changeType: "positive",
    },
    {
      title: "Tổng công nợ",
      value: "2.4M VNĐ",
      icon: DollarSign,
      change: "-5%",
      changeType: "negative",
    },
    {
      title: "Doanh thu",
      value: "15.8M VNĐ",
      icon: TrendingUp,
      change: "+23%",
      changeType: "positive",
    },
  ]

  const recentInvoices = [
    {
      id: "INV-001",
      supplier: "Công ty TNHH ABC",
      amount: "500,000 VNĐ",
      status: "paid",
      date: "2024-01-15",
    },
    {
      id: "INV-002",
      supplier: "Công ty XYZ",
      amount: "750,000 VNĐ",
      status: "pending",
      date: "2024-01-14",
    },
    {
      id: "INV-003",
      supplier: "Nhà cung cấp DEF",
      amount: "1,200,000 VNĐ",
      status: "overdue",
      date: "2024-01-10",
    },
  ]

  const topSuppliers = [
    {
      name: "Công ty TNHH ABC",
      rating: 4.8,
      totalOrders: 45,
      totalAmount: "12.5M VNĐ",
    },
    {
      name: "Công ty XYZ",
      rating: 4.6,
      totalOrders: 38,
      totalAmount: "9.8M VNĐ",
    },
    {
      name: "Nhà cung cấp DEF",
      rating: 4.4,
      totalOrders: 32,
      totalAmount: "8.2M VNĐ",
    },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Dashboard</h1>
        <p className="text-muted-foreground">Tổng quan hệ thống quản lý nhà cung cấp</p>
      </div>

      {/* Stats Cards */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        {stats.map((stat, index) => (
          <Card key={index}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{stat.title}</CardTitle>
              <stat.icon className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stat.value}</div>
              <p className={`text-xs ${stat.changeType === "positive" ? "text-green-600" : "text-red-600"}`}>
                {stat.change} so với tháng trước
              </p>
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid gap-6 md:grid-cols-2">
        {/* Recent Invoices */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FileText className="h-5 w-5" />
              Hóa đơn gần đây
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {recentInvoices.map((invoice) => (
                <div key={invoice.id} className="flex items-center justify-between">
                  <div>
                    <p className="font-medium">{invoice.id}</p>
                    <p className="text-sm text-muted-foreground">{invoice.supplier}</p>
                  </div>
                  <div className="text-right">
                    <p className="font-medium">{invoice.amount}</p>
                    <Badge
                      variant={
                        invoice.status === "paid"
                          ? "default"
                          : invoice.status === "pending"
                            ? "secondary"
                            : "destructive"
                      }
                    >
                      {invoice.status === "paid"
                        ? "Đã thanh toán"
                        : invoice.status === "pending"
                          ? "Chờ thanh toán"
                          : "Quá hạn"}
                    </Badge>
                  </div>
                </div>
              ))}
            </div>
            <div className="mt-4">
              <Button asChild variant="outline" className="w-full">
                <Link href="/invoices">Xem tất cả hóa đơn</Link>
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* Top Suppliers */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Building className="h-5 w-5" />
              Nhà cung cấp hàng đầu
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {topSuppliers.map((supplier, index) => (
                <div key={index} className="flex items-center justify-between">
                  <div>
                    <p className="font-medium">{supplier.name}</p>
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                      <span>⭐ {supplier.rating}</span>
                      <span>•</span>
                      <span>{supplier.totalOrders} đơn hàng</span>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className="font-medium">{supplier.totalAmount}</p>
                  </div>
                </div>
              ))}
            </div>
            <div className="mt-4">
              <Button asChild variant="outline" className="w-full">
                <Link href="/suppliers">Xem tất cả nhà cung cấp</Link>
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Quick Actions */}
      <Card>
        <CardHeader>
          <CardTitle>Thao tác nhanh</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Button asChild className="h-20 flex-col">
              <Link href="/suppliers/new">
                <Users className="h-6 w-6 mb-2" />
                Thêm nhà cung cấp
              </Link>
            </Button>
            <Button asChild variant="outline" className="h-20 flex-col">
              <Link href="/invoices/new">
                <FileText className="h-6 w-6 mb-2" />
                Tạo hóa đơn
              </Link>
            </Button>
            <Button asChild variant="outline" className="h-20 flex-col">
              <Link href="/payments">
                <DollarSign className="h-6 w-6 mb-2" />
                Thanh toán
              </Link>
            </Button>
            <Button asChild variant="outline" className="h-20 flex-col">
              <Link href="/cashbook">
                <TrendingUp className="h-6 w-6 mb-2" />
                Sổ quỹ
              </Link>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
