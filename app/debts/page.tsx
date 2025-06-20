"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Search, AlertTriangle, DollarSign, Calendar } from "lucide-react"
import Link from "next/link"

interface Debt {
  id: string
  supplier: string
  totalAmount: string
  paidAmount: string
  remainingAmount: string
  dueDate: string
  status: "current" | "overdue" | "paid"
  invoices: string[]
}

export default function DebtsPage() {
  const [searchTerm, setSearchTerm] = useState("")

  const debts: Debt[] = [
    {
      id: "DEBT-001",
      supplier: "Công ty TNHH ABC",
      totalAmount: "2,500,000 VNĐ",
      paidAmount: "1,500,000 VNĐ",
      remainingAmount: "1,000,000 VNĐ",
      dueDate: "2024-02-15",
      status: "current",
      invoices: ["INV-001", "INV-005"],
    },
    {
      id: "DEBT-002",
      supplier: "Công ty XYZ",
      totalAmount: "1,800,000 VNĐ",
      paidAmount: "0 VNĐ",
      remainingAmount: "1,800,000 VNĐ",
      dueDate: "2024-01-25",
      status: "overdue",
      invoices: ["INV-002", "INV-006"],
    },
    {
      id: "DEBT-003",
      supplier: "Nhà cung cấp DEF",
      totalAmount: "3,200,000 VNĐ",
      paidAmount: "3,200,000 VNĐ",
      remainingAmount: "0 VNĐ",
      dueDate: "2024-01-20",
      status: "paid",
      invoices: ["INV-003", "INV-007"],
    },
    {
      id: "DEBT-004",
      supplier: "Công ty GHI",
      totalAmount: "950,000 VNĐ",
      paidAmount: "200,000 VNĐ",
      remainingAmount: "750,000 VNĐ",
      dueDate: "2024-02-10",
      status: "current",
      invoices: ["INV-004"],
    },
  ]

  const filteredDebts = debts.filter(
    (debt) =>
      debt.supplier.toLowerCase().includes(searchTerm.toLowerCase()) ||
      debt.id.toLowerCase().includes(searchTerm.toLowerCase()),
  )

  // Calculate summary
  const totalDebt = debts
    .filter((debt) => debt.status !== "paid")
    .reduce((sum, debt) => sum + Number.parseFloat(debt.remainingAmount.replace(/[^\d]/g, "")), 0)

  const overdueDebt = debts
    .filter((debt) => debt.status === "overdue")
    .reduce((sum, debt) => sum + Number.parseFloat(debt.remainingAmount.replace(/[^\d]/g, "")), 0)

  const currentDebt = debts
    .filter((debt) => debt.status === "current")
    .reduce((sum, debt) => sum + Number.parseFloat(debt.remainingAmount.replace(/[^\d]/g, "")), 0)

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "paid":
        return <Badge className="bg-green-100 text-green-800">Đã thanh toán</Badge>
      case "current":
        return <Badge className="bg-blue-100 text-blue-800">Hiện tại</Badge>
      case "overdue":
        return <Badge className="bg-red-100 text-red-800">Quá hạn</Badge>
      default:
        return <Badge variant="secondary">{status}</Badge>
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Quản lý công nợ</h1>
        <p className="text-muted-foreground">Theo dõi và quản lý công nợ với nhà cung cấp</p>
      </div>

      {/* Summary Cards */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Tổng công nợ</CardTitle>
            <DollarSign className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-red-600">{totalDebt.toLocaleString()} VNĐ</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Công nợ quá hạn</CardTitle>
            <AlertTriangle className="h-4 w-4 text-red-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-red-600">{overdueDebt.toLocaleString()} VNĐ</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Công nợ hiện tại</CardTitle>
            <Calendar className="h-4 w-4 text-blue-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-blue-600">{currentDebt.toLocaleString()} VNĐ</div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Danh sách công nợ</CardTitle>
          <div className="flex space-x-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
              <Input
                placeholder="Tìm kiếm theo nhà cung cấp, mã công nợ..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10"
              />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Mã công nợ</TableHead>
                <TableHead>Nhà cung cấp</TableHead>
                <TableHead>Tổng tiền</TableHead>
                <TableHead>Đã thanh toán</TableHead>
                <TableHead>Còn lại</TableHead>
                <TableHead>Ngày đến hạn</TableHead>
                <TableHead>Trạng thái</TableHead>
                <TableHead>Thao tác</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredDebts.map((debt) => (
                <TableRow key={debt.id}>
                  <TableCell className="font-medium">{debt.id}</TableCell>
                  <TableCell>{debt.supplier}</TableCell>
                  <TableCell>{debt.totalAmount}</TableCell>
                  <TableCell className="text-green-600">{debt.paidAmount}</TableCell>
                  <TableCell className="font-medium text-red-600">{debt.remainingAmount}</TableCell>
                  <TableCell>{debt.dueDate}</TableCell>
                  <TableCell>{getStatusBadge(debt.status)}</TableCell>
                  <TableCell>
                    <div className="flex space-x-2">
                      <Button asChild variant="outline" size="sm">
                        <Link href={`/payments?debt=${debt.id}`}>Thanh toán</Link>
                      </Button>
                      <Button variant="ghost" size="sm">
                        Chi tiết
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
