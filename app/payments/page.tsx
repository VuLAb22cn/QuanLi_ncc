"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Plus, Search, CheckCircle, Clock, AlertCircle } from "lucide-react"

interface Payment {
  id: string
  supplier: string
  amount: string
  method: string
  date: string
  status: "completed" | "pending" | "failed"
  reference: string
  description: string
}

export default function PaymentsPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [statusFilter, setStatusFilter] = useState("all")
  const [isDialogOpen, setIsDialogOpen] = useState(false)

  const payments: Payment[] = [
    {
      id: "PAY-001",
      supplier: "Công ty TNHH ABC",
      amount: "500,000 VNĐ",
      method: "Chuyển khoản",
      date: "2024-01-15",
      status: "completed",
      reference: "INV-001",
      description: "Thanh toán hóa đơn vận chuyển",
    },
    {
      id: "PAY-002",
      supplier: "Công ty XYZ",
      amount: "750,000 VNĐ",
      method: "Tiền mặt",
      date: "2024-01-14",
      status: "pending",
      reference: "INV-002",
      description: "Thanh toán dịch vụ bảo trì",
    },
    {
      id: "PAY-003",
      supplier: "Nhà cung cấp DEF",
      amount: "1,200,000 VNĐ",
      method: "Chuyển khoản",
      date: "2024-01-13",
      status: "failed",
      reference: "INV-003",
      description: "Thanh toán tư vấn IT",
    },
    {
      id: "PAY-004",
      supplier: "Công ty GHI",
      amount: "300,000 VNĐ",
      method: "Chuyển khoản",
      date: "2024-01-12",
      status: "completed",
      reference: "INV-004",
      description: "Thanh toán văn phòng phẩm",
    },
  ]

  const filteredPayments = payments.filter((payment) => {
    const matchesSearch =
      payment.supplier.toLowerCase().includes(searchTerm.toLowerCase()) ||
      payment.reference.toLowerCase().includes(searchTerm.toLowerCase()) ||
      payment.id.toLowerCase().includes(searchTerm.toLowerCase())
    const matchesStatus = statusFilter === "all" || payment.status === statusFilter
    return matchesSearch && matchesStatus
  })

  const getStatusBadge = (status: string) => {
    switch (status) {
      case "completed":
        return (
          <Badge className="bg-green-100 text-green-800">
            <CheckCircle className="w-3 h-3 mr-1" />
            Hoàn thành
          </Badge>
        )
      case "pending":
        return (
          <Badge className="bg-yellow-100 text-yellow-800">
            <Clock className="w-3 h-3 mr-1" />
            Đang xử lý
          </Badge>
        )
      case "failed":
        return (
          <Badge className="bg-red-100 text-red-800">
            <AlertCircle className="w-3 h-3 mr-1" />
            Thất bại
          </Badge>
        )
      default:
        return <Badge variant="secondary">{status}</Badge>
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold">Quản lý thanh toán</h1>
          <p className="text-muted-foreground">Theo dõi và thực hiện thanh toán cho nhà cung cấp</p>
        </div>
        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
          <DialogTrigger asChild>
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              Tạo thanh toán
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-2xl">
            <DialogHeader>
              <DialogTitle>Tạo thanh toán mới</DialogTitle>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="supplier">Nhà cung cấp</Label>
                  <Select>
                    <SelectTrigger>
                      <SelectValue placeholder="Chọn nhà cung cấp" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="abc">Công ty TNHH ABC</SelectItem>
                      <SelectItem value="xyz">Công ty XYZ</SelectItem>
                      <SelectItem value="def">Nhà cung cấp DEF</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="amount">Số tiền</Label>
                  <Input id="amount" placeholder="Nhập số tiền" />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="method">Phương thức</Label>
                  <Select>
                    <SelectTrigger>
                      <SelectValue placeholder="Chọn phương thức" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="transfer">Chuyển khoản</SelectItem>
                      <SelectItem value="cash">Tiền mặt</SelectItem>
                      <SelectItem value="check">Séc</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="reference">Mã tham chiếu</Label>
                  <Input id="reference" placeholder="Mã hóa đơn, công nợ..." />
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="description">Mô tả</Label>
                <Textarea id="description" placeholder="Mô tả chi tiết thanh toán" />
              </div>
              <div className="flex justify-end space-x-2">
                <Button variant="outline" onClick={() => setIsDialogOpen(false)}>
                  Hủy
                </Button>
                <Button onClick={() => setIsDialogOpen(false)}>Tạo thanh toán</Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Lịch sử thanh toán</CardTitle>
          <div className="flex space-x-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
              <Input
                placeholder="Tìm kiếm theo nhà cung cấp, mã thanh toán..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10"
              />
            </div>
            <Select value={statusFilter} onValueChange={setStatusFilter}>
              <SelectTrigger className="w-48">
                <SelectValue placeholder="Lọc theo trạng thái" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Tất cả</SelectItem>
                <SelectItem value="completed">Hoàn thành</SelectItem>
                <SelectItem value="pending">Đang xử lý</SelectItem>
                <SelectItem value="failed">Thất bại</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Mã thanh toán</TableHead>
                <TableHead>Nhà cung cấp</TableHead>
                <TableHead>Số tiền</TableHead>
                <TableHead>Phương thức</TableHead>
                <TableHead>Ngày</TableHead>
                <TableHead>Trạng thái</TableHead>
                <TableHead>Tham chiếu</TableHead>
                <TableHead>Thao tác</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredPayments.map((payment) => (
                <TableRow key={payment.id}>
                  <TableCell className="font-medium">{payment.id}</TableCell>
                  <TableCell>{payment.supplier}</TableCell>
                  <TableCell className="font-medium">{payment.amount}</TableCell>
                  <TableCell>{payment.method}</TableCell>
                  <TableCell>{payment.date}</TableCell>
                  <TableCell>{getStatusBadge(payment.status)}</TableCell>
                  <TableCell>{payment.reference}</TableCell>
                  <TableCell>
                    <div className="flex space-x-2">
                      <Button variant="ghost" size="sm">
                        Chi tiết
                      </Button>
                      {payment.status === "failed" && (
                        <Button variant="outline" size="sm">
                          Thử lại
                        </Button>
                      )}
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
