import googleClassroom from '@/lib/config/googleClassroom';
import { formatDate } from '@/lib/utils/format';
import {
  Container,
  Title,
  Text,
  Divider,
  Flex,
  Button,
  Stack,
  Skeleton,
  Loader,
} from '@mantine/core';
import React, { Suspense } from 'react';
import SubmissionCount from './SubmissionCount';

type Props = {
  params: {
    courseId: string;
    id: string;
  };
};

const getCourseWork = async (courseId: string, id: string) => {
  const classroom = await googleClassroom();
  const { data: courseWork } = await classroom.courses.courseWork.get({
    courseId,
    id,
  });
  return courseWork;
};

export async function generateMetadata({ params: { courseId, id } }: Props) {
  const courseWork = await getCourseWork(courseId, id);
  return {
    title: courseWork.title,
  };
}

export default async function CourseWorkPage({
  params: { id, courseId },
}: Props) {
  const courseWork = await getCourseWork(courseId, id);

  return (
    <Container mt='lg' size='md'>
      <Title>{courseWork.title}</Title>
      <Flex justify={'space-between'}>
        <div>
          <Text tt='capitalize'>
            {courseWork.workType?.toLowerCase()}
            <Text component={'span'} c='dimmed' size='sm'>
              &nbsp; ({courseWork.maxPoints} Points)
            </Text>
          </Text>
          <Text size='sm' c='dimmed'>
            Due: {formatDate(courseWork.dueDate, courseWork.dueTime)}
          </Text>
          <Text tt='capitalize' size='sm' c='dimmed'>
            {courseWork.state?.toLocaleLowerCase()}
          </Text>
        </div>
        <Stack gap={5}>
          <Button
            variant='light'
            size='sm'
            rightSection={
              <Suspense fallback={<Loader color='blue' size='1.1rem' />}>
                <SubmissionCount courseId={courseId} courseWorkId={id} />
              </Suspense>
            }
          >
            Student Work
          </Button>
        </Stack>
      </Flex>
      <Divider my='md' />
      <Text>{courseWork.description}</Text>
    </Container>
  );
}
