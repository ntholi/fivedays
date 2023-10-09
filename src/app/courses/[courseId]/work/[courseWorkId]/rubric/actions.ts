'use server';
import prisma from '@/lib/db';
import { revalidatePath } from 'next/cache';
import { z } from 'zod';

const schema = z.object({
  title: z.string(),
  description: z.string().optional(),
  points: z.coerce.number().positive(),
});

export async function addRubricItem(
  courseId: string,
  courseWorkId: string,
  formData: FormData
) {
  const data = schema.parse(Object.fromEntries(formData.entries()));

  await prisma.rubricItem.create({
    data: {
      rubric: {
        connectOrCreate: {
          where: {
            courseId_courseWorkId: {
              courseId: courseId,
              courseWorkId: courseWorkId,
            },
          },
          create: {
            courseId: courseId,
            courseWorkId: courseWorkId,
          },
        },
      },
      ...data,
    },
  });

  revalidatePath(`/courses/${courseId}/work/${courseWorkId}/rubric`);
}
